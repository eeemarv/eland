<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TransactionService;
use App\Service\TypeaheadService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;
use Psr\Log\LoggerInterface;

class MassTransactionController extends AbstractController
{
    const STATUS_RENDER = [
        'active'	=> [
            'lbl'	=> 'Actief',
            'st'	=> 1,
            'hsh'	=> '58d267',
        ],
        'without-new-and-leaving' => [
            'lbl'	=> 'Actief zonder uit- en instappers',
            'st'	=> '123',
            'hsh'	=> '096024',
        ],
        'new'		=> [
            'lbl'	=> 'Instappers',
            'st'	=> 3,
            'hsh'	=> 'e25b92',
            'cl'	=> 'success',
        ],
        'leaving'	=> [
            'lbl'	=> 'Uitstappers',
            'st'	=> 2,
            'hsh'	=> 'ea4d04',
            'cl'	=> 'danger',
        ],
        'inactive'	=> [
            'lbl'	=> 'Inactief',
            'st'	=> 0,
            'hsh'	=> '79a240',
            'cl'	=> 'inactive',
        ],
        'info-packet'	=> [
            'lbl'	=> 'Info-pakket',
            'st'	=> 5,
            'hsh'	=> '2ed157',
            'cl'	=> 'warning',
        ],
        'info-moment'	=> [
            'lbl'	=> 'Info-moment',
            'st'	=> 6,
            'hsh'	=> '065878',
            'cl'	=> 'info',
        ],
        'all'		=> [
            'lbl'	=> 'Alle',
        ],
    ];

    const STATUS = [
        0 	=> 'inactive',
        1 	=> 'active',
        2 	=> 'leaving',
        3	=> 'new',
        5	=> 'info-packet',
        6	=> 'info-moment',
        7	=> 'extern',
        123 => 'without-new-and-leaving',
    ];

    public function __invoke(
        Predis $predis,
        Request $request,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        MenuService $menu_service,
        LinkRender $link_render,
        HeadingRender $heading_render,
        MailQueue $mail_queue,
        TypeaheadService $typeahead_service,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        AutoMinLimitService $autominlimit_service,
        TransactionService $transaction_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        AssetsService $assets_service
    ):Response
    {
        $errors = [];

        $q = $request->get('q', '');
        $hsh = $request->get('hsh', '096024');

        $selected_users = $request->request->get('selected_users', '');
        $selected_users = ltrim($selected_users, '.');
        $selected_users = explode('.', $selected_users);
        $selected_users = array_combine($selected_users, $selected_users);

        $users = [];

        $rs = $db->prepare(
            'select id, name, letscode,
                accountrole, status, saldo,
                minlimit, maxlimit, adate,
                postcode
            from ' . $pp->schema() . '.users
            where status IN (0, 1, 2, 5, 6)
            order by letscode');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $row['minlimit'] = !isset($row['minlimit'])
                    || $row['minlimit'] === -999999999
                ? ''
                : $row['minlimit'];
            $row['maxlimit'] = !isset($row['maxlimit'])
                || $row['maxlimit'] === 999999999
                ? ''
                : $row['maxlimit'];

            $users[$row['id']] = $row;
        }

        $to_letscode = trim($request->request->get('to_letscode', ''));

        if ($to_letscode !== '')
        {
            [$to_letscode] = explode(' ', $to_letscode);
        }

        $from_letscode = trim($request->request->get('from_letscode', ''));

        if ($from_letscode !== '')
        {
            [$from_letscode] = explode(' ', $from_letscode);
        }

        $amount = $request->request->get('amount', []);
        $description = trim($request->request->get('description', ''));
        $transid = $request->request->get('transid', '');
        $mail_en = $request->request->get('mail_en', false);

        if ($request->isMethod('POST'))
        {
            if (!$request->request->get('verify', false))
            {
                $errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
            }

            if (!$description)
            {
                $errors[] = 'Vul een omschrijving in.';
            }

            if ($to_letscode && $from_letscode)
            {
                $errors[] = '\'Van Account Code\' en \'Aan Account Code\' kunnen niet beide ingevuld worden.';
            }
            else if (!($to_letscode || $from_letscode))
            {
                $errors[] = '\'Van Account Code\' OF \'Aan Account Code\' moet ingevuld worden.';
            }
            else
            {
                $to_one = $to_letscode ? true : false;
                $letscode = $to_one ? $to_letscode : $from_letscode;

                $one_uid = $db->fetchColumn('select id
                    from ' . $pp->schema() . '.users
                    where letscode = ?', [$letscode]);

                if (!$one_uid)
                {
                    $err = 'Geen bestaande Account Code in veld \'';
                    $err .= $to_one ? 'Aan': 'Van';
                    $err .= ' Account Code\'.';
                    $errors[] = $err;
                }
                else
                {
                    unset($amount[$one_uid]);
                }
            }

            $filter_options = [
                'options'	=> [
                    'min_range' => 0,
                ],
            ];

            $count = 0;

            foreach ($amount as $uid => $amo)
            {
                if (!isset($selected_users[$uid]))
                {
                    continue;
                }

                if (!$amo)
                {
                    continue;
                }

                $count++;

                if (!filter_var($amo, FILTER_VALIDATE_INT, $filter_options))
                {
                    $errors[] = 'Ongeldig bedrag ingevuld.';
                    break;
                }
            }

            if (!$count)
            {
                $errors[] = 'Er is geen enkel bedrag ingevuld.';
            }

            if (!$transid)
            {
                $errors[] = 'Geen geldig transactie id';
            }

            if ($db->fetchColumn('select id
                from ' . $pp->schema() . '.transactions
                where transid = ?', [$transid]))
            {
                $errors[] = 'Een dubbele boeking van een transactie werd voorkomen.';
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (count($errors))
            {
                $alert_service->error($errors);
            }
            else
            {
                $transactions = [];

                $db->beginTransaction();

                $cdate = gmdate('Y-m-d H:i:s');

                $alert_success = $log_many = '';
                $total_amount = 0;

                try
                {

                    foreach ($amount as $many_uid => $amo)
                    {
                        if (!isset($selected_users[$many_uid]))
                        {
                            continue;
                        }

                        if (!$amo || $many_uid == $one_uid)
                        {
                            continue;
                        }

                        $many_user = $users[$many_uid];
                        $to_id = $to_one ? $one_uid : $many_uid;
                        $from_id = $to_one ? $many_uid : $one_uid;
                        $from_user = $users[$from_id];
                        $to_user = $users[$to_id];

                        $alert_success .= 'Transactie van gebruiker ' . $from_user['letscode'] . ' ' . $from_user['name'];
                        $alert_success .= ' naar ' . $to_user['letscode'] . ' ' . $to_user['name'];
                        $alert_success .= '  met bedrag ' . $amo .' ';
                        $alert_success .= $config_service->get('currency', $pp->schema());
                        $alert_success .= ' uitgevoerd.<br>';

                        $log_many .= $many_user['letscode'] . ' ' . $many_user['name'] . '(' . $amo . '), ';

                        $transaction = [
                            'id_to' 		=> $to_id,
                            'id_from' 		=> $from_id,
                            'amount' 		=> $amo,
                            'description' 	=> $description,
                            'cdate' 		=> $cdate,
                            'transid'		=> $transid,
                            'creator'		=> $su->is_master() ? 0 : $su->id(),
                        ];

                        $db->insert($pp->schema() . '.transactions', $transaction);
                        $transaction['id'] = $db->lastInsertId($pp->schema() . '.transactions_id_seq');

                        $db->executeUpdate('update ' . $pp->schema() . '.users
                            set saldo = saldo ' . (($to_one) ? '- ' : '+ ') . '?
                            where id = ?', [$amo, $many_uid]);

                        $total_amount += $amo;

                        $transid = $transaction_service->generate_transid($su->id(), $pp->system());

                        $transactions[] = $transaction;
                    }

                    $db->executeUpdate('update ' . $pp->schema() . '.users
                        set saldo = saldo ' . (($to_one) ? '+ ' : '- ') . '?
                        where id = ?', [$total_amount, $one_uid]);

                    $db->commit();
                }
                catch (Exception $e)
                {
                    $alert_service->error('Fout bij het opslaan.');
                    $db->rollback();
                    throw $e;
                }

                $autominlimit_service->init($pp->schema());

                foreach($transactions as $t)
                {
                    $autominlimit_service->process($t['id_from'], $t['id_to'], (int) $t['amount']);
                }

                if ($to_one)
                {
                    foreach ($transactions as $t)
                    {
                        $predis->del($pp->schema() . '_user_' . $t['id_from']);
                    }

                    $predis->del($pp->schema() . '_user_' . $t['id_to']);
                }
                else
                {
                    foreach ($transactions as $t)
                    {
                        $predis->del($pp->schema() . '_user_' . $t['id_to']);
                    }

                    $predis->del($pp->schema() . '_user_' . $t['id_from']);
                }

                $alert_success .= 'Totaal: ' . $total_amount . ' ';
                $alert_success .= $config_service->get('currency', $pp->schema());
                $alert_service->success($alert_success);

                $log_one = $users[$one_uid]['letscode'] . ' ';
                $log_one .= $users[$one_uid]['name'];
                $log_one .= '(Total amount: ' . $total_amount . ' ';
                $log_one .= $config_service->get('currency', $pp->schema());
                $log_one .= ')';

                $log_many = rtrim($log_many, ', ');
                $log_str = 'Mass transaction from ';
                $log_str .= $to_one ? $log_many : $log_one;
                $log_str .= ' to ';
                $log_str .= $to_one ? $log_one : $log_many;

                $logger->info('trans: ' . $log_str, ['schema' => $pp->schema()]);

                if ($su->is_master())
                {
                    $alert_service->warning('Master account: geen mails verzonden.');
                }
                else if ($mail_en)
                {
                    foreach ($transactions as $transaction)
                    {
                        $user_id = $to_one ? $transaction['id_from'] : $transaction['id_to'];

                        $vars = [
                            'transaction'	=> $transaction,
                            'from_user_id'	=> $transaction['id_from'],
                            'to_user_id'	=> $transaction['id_to'],
                            'user_id'		=> $user_id,
                        ];

                        $mail_queue->queue([
                            'schema'	=> $pp->schema(),
                            'to'		=> $mail_addr_user_service->get_active($user_id, $pp->schema()),
                            'template'	=> 'transaction/transaction',
                            'vars'		=> $vars,
                        ], random_int(0, 5000));
                    }

                    $vars = [
                        'transactions'	=> $transactions,
                        'total_amount'	=> $total_amount,
                        'description'	=> $description,
                    ];

                    if ($to_one)
                    {
                        $vars['to_user_id'] = $one_uid;
                    }
                    else
                    {
                        $vars['from_user_id'] = $one_uid;
                    }

                    $mail_template = 'mass_transaction/';
                    $mail_template .= $to_one ? 'many_to_one' : 'one_to_many';

                    $mail_queue->queue([
                        'schema'	=> $pp->schema(),
                        'to' 		=> array_merge(
                            $mail_addr_system_service->get_admin($pp->schema()),
                            $mail_addr_user_service->get_active($su->id(), $pp->schema()),
                            $mail_addr_user_service->get_active($one_uid, $pp->schema())
                        ),
                        'template'	=> $mail_template,
                        'vars'		=> $vars,
                    ], 8000);

                    $alert_service->success('Notificatie mails verzonden.');
                }

                $link_render->redirect('mass_transaction', $pp->ary(), []);
            }
        }
        else
        {
            $mail_en = true;
        }

        $transid = $transaction_service->generate_transid($su->id(), $pp->system());

        if ($to_letscode)
        {
            if ($to_name = $db->fetchColumn('select name
                from ' . $pp->schema() . '.users
                where letscode = ?', [$to_letscode]))
            {
                $to_letscode .= ' ' . $to_name;
            }
        }
        if ($from_letscode)
        {
            if ($from_name = $db->fetchColumn('select name
                from ' . $pp->schema() . '.users
                where letscode = ?', [$from_letscode]))
            {
                $from_letscode .= ' ' . $from_name;
            }
        }

        $system_minlimit = $config_service->get('minlimit', $pp->schema());
        $system_maxlimit = $config_service->get('maxlimit', $pp->schema());

        $assets_service->add([
            'mass_transaction.js',
            'combined_filter.js',
        ]);

        $heading_render->add('Massa transactie');
        $heading_render->fa('exchange');

        $out = '<div class="panel panel-warning">';
        $out .= '<div class="panel-heading">';
        $out .= '<button class="btn btn-default btn-lg" ';
        $out .= 'title="Toon invul-hulp" data-toggle="collapse" ';
        $out .= 'data-target="#help" type="button">';
        $out .= '<i class="fa fa-question"></i>';
        $out .= ' Invul-hulp</button>';
        $out .= '</div>';
        $out .= '<div class="panel-heading collapse" id="help">';

        $out .= '<p>Met deze invul-hulp kan je snel alle ';
        $out .= 'bedragen van de massa-transactie invullen. ';
        $out .= 'De bedragen kan je nadien nog individueel ';
        $out .= 'aanpassen alvorens de massa transactie uit te voeren. ';
        $out .= '</p>';

        $out .= '<form class="form" id="fill_in_aid" ';

        $out .= 'data-transactions-sum-in="';
        $out .= htmlspecialchars($link_render->context_path('transactions_sum_in',
            $pp->ary(), ['days' => 365]));
        $out .= '" ';

        $out .= 'data-transactions-sum-out="';
        $out .= htmlspecialchars($link_render->context_path('transactions_sum_out',
            $pp->ary(), ['days' => 365]));
        $out .= '" ';

        $out .= 'data-weighted-balances="';
        $out .= htmlspecialchars($link_render->context_path('weighted_balances',
            $pp->ary(), ['days' => 365]));
        $out .= '"';

        $out .= '>';

        $out .= '<div class="pan-sub bg-warning">';

        $out .= '<div class="form-group">';
        $out .= '<label for="fixed" class="control-label">';
        $out .= 'Vast bedrag</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</span>';
        $out .= '<input type="number" class="form-control margin-bottom" id="fixed" ';
        $out .= 'min="0">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        /**/
        $out .= '<div class="pan-sub bg-warning">';

        $out .= '<h4>Variabel deel</h4>';

        $out .= '<div class="form-group">';
        $out .= '<label for="fixed" class="control-label">';
        $out .= 'Over periode</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= 'dagen</span>';
        $out .= '<input type="number" ';
        $out .= 'class="form-control margin-bottom" id="var_days" ';
        $out .= 'min="0">';
        $out .= '</div>';
        $out .= '</div>';

        //
        $out .= '<div class="form-group">';
        $out .= '<label for="var_balance" class="control-label">';
        $out .= 'Promille op saldo</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-6">';

        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">&permil;</span>';
        $out .= '<input type="number" ';
        $out .= 'class="form-control margin-bottom" id="var_balance">';
        $out .= '</div>';
        $out .= '<p>Berekend op gewogen gemiddelde van saldo. ';
        $out .= 'Kan ook negatief zijn!</p>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= ': basis';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="var_base">';
        $out .= '</div>';
        $out .= '<p>De basis waartegenover berekend wordt. ';
        $out .= 'Kan ook afwijkend van nul zijn.</p>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        //
        $out .= '<div class="form-group">';
        $out .= '<label for="var_trans_in" class="control-label">';
        $out .= 'Promille op transacties in</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">&permil;</span>';
        $out .= '<input type="number" class="form-control" id="var_trans_in">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= 'excl. ';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="var_ex_code_in" ';
        $out .= 'placeholder="Account Codes">';
        $out .= '</div>';
        $out .= '<p>Exclusief tegenpartijen: ';
        $out .= 'Account Codes gescheiden door komma\'s</p>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        //
        $out .= '<div class="form-group">';
        $out .= '<label for="var_trans_out" class="control-label">';
        $out .= 'Promille op transacties uit</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">&permil;</span>';
        $out .= '<input type="number" class="form-control" id="var_trans_out">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= 'excl. ';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="var_ex_code_out" ';
        $out .= 'placeholder="Account Codes">';
        $out .= '</div>';
        $out .= '<p>Exclusief tegenpartijen: ';
        $out .= 'Account Codes gescheiden door komma\'s</p>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        //
        $out .= '<div class="form-group">';
        $out .= '<label for="var_minimum" class="control-label">';
        $out .= 'Minimum - maximum</label>';
        $out .= '<div class="row">';
        $out .= '<div class="col-sm-6">';

        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= ': min';
        $out .= '</span>';

        $out .= '<input type="number" ';
        $out .= 'class="form-control margin-bottom" id="var_min">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= ': max';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="var_max">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';
        /**/

        $out .= '<div class="form-group">';
        $out .= '<label for="respect_minlimit" class="control-label">';
        $out .= '<input type="checkbox" id="respect_minlimit" checked="checked">';
        $out .= ' Respecteer minimum limieten</label>';
        $out .= '</div>';

        if ($config_service->get('minlimit', $pp->schema()) !== ''
            || $config_service->get('maxlimit', $pp->schema()) !== '')
        {
            $out .= '<ul>';

            if ($config_service->get('minlimit', $pp->schema()) !== '')
            {
                $out .= '<li>Minimum Systeemslimiet: ';
                $out .= $config_service->get('minlimit', $pp->schema());
                $out .= ' ';
                $out .= $config_service->get('currency', $pp->schema());
                $out .= '</li>';
            }

            if ($config_service->get('maxlimit', $pp->schema()) !== '')
            {
                $out .= '<li>Maximum Systeemslimiet: ';
                $out .= $config_service->get('maxlimit', $pp->schema());
                $out .= ' ';
                $out .= $config_service->get('currency', $pp->schema());
                $out .= '</li>';
            }

            $out .= '<li>De Systeemslimieten gelden voor alle Accounts behalve de ';
            $out .= 'Accounts waarbij individuele limieten ingesteld zijn.</li>';

            $out .= '</ul>';
        }

        $out .= '<button class="btn btn-default btn-lg" id="fill-in">';
        $out .= 'Vul in</button>';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get">';
        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="q" name="q" value="';
        $out .= $q;
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<ul class="nav nav-tabs" id="nav-tabs">';

        foreach (self::STATUS_RENDER as $k => $s)
        {
            $shsh = $s['hsh'] ?? '';
            $class_li = $shsh == $hsh ? ' class="active"' : '';
            $class_a  = $s['cl'] ?? 'white';

            $out .= '<li' . $class_li . '><a href="#" class="bg-' . $class_a . '" ';
            $out .= 'data-filter="' . $shsh . '">' . $s['lbl'] . '</a></li>';
        }

        $out .= '</ul>';

        $out .= '<form method="post" autocomplete="off">';

        $out .= '<input type="hidden" value="" id="combined-filter">';
        $out .= '<input type="hidden" value="';
        $out .= $hsh;
        $out .= '" name="hsh" id="hsh">';
        $out .= '<input type="hidden" value="" ';
        $out .= 'name="selected_users" id="selected_users">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<div class="form-group">';
        $out .= '<label for="from_letscode" class="control-label">';
        $out .= 'Van Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="from_letscode" name="from_letscode" ';
        $out .= 'value="';
        $out .= $from_letscode;
        $out .= '" ';

        $out .= 'data-typeahead="';
        $out .= $typeahead_service->ini($pp->ary())
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->add('accounts', ['status' => 'extern'])
            ->str([
                'filter'        => 'accounts',
                'newuserdays'   => $config_service->get('newuserdays', $pp->schema()),
            ]);
        $out .= '">';

        $out .= '</div>';
        $out .= '<p>Gebruik dit voor een "Eén naar veel" transactie.';
        $out .= 'Alle ingevulde bedragen hieronder ';
        $out .= 'worden van dit Account gehaald.</p>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover panel-body footable" ';
        $out .= 'data-filter="#combined-filter" data-filter-minimum="1" ';
        $out .= 'data-minlimit="';
        $out .= $system_minlimit;
        $out .= '" ';
        $out .= 'data-maxlimit="';
        $out .= $system_maxlimit;
        $out .= '"';
        $out .= '>';
        $out .= '<thead>';

        $out .= '<tr>';
        $out .= '<th data-sort-initial="true">Code</th>';
        $out .= '<th data-filter="#filter">Naam</th>';
        $out .= '<th data-sort-ignore="true">Bedrag</th>';
        $out .= '<th data-hide="phone">Saldo</th>';
        $out .= '<th data-hide="phone">Min.limit</th>';
        $out .= '<th data-hide="phone">Max.limit</th>';
        $out .= '<th data-hide="phone, tablet">Postcode</th>';
        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        foreach($users as $user_id => $user)
        {
            $status_key = self::STATUS[$user['status']];

            if (isset($user['adate']))
            {
                $status_key = ($status_key == 'active' && $config_service->get_new_user_treshold($pp->schema()) < strtotime($user['adate'])) ? 'new' : $status_key;
            }

            $hsh = self::STATUS_RENDER[$status_key]['hsh'] ?: '';
            $hsh .= $status_key == 'leaving' || $status_key == 'new' ? self::STATUS_RENDER['active']['hsh'] : '';
            $hsh .= $status_key == 'active' ? self::STATUS_RENDER['without-new-and-leaving']['hsh'] : '';

            $class = isset(self::STATUS_RENDER[$status_key]['cl']) ? ' class="' . self::STATUS_RENDER[$status_key]['cl'] . '"' : '';

            $out .= '<tr' . $class . ' data-user-id="' . $user_id . '">';

            $out .= '<td>';

            $out .= $link_render->link_no_attr($vr->get('users_show'), $pp->ary(),
                ['id' => $user_id], $user['letscode']);

            $out .= '</td>';

            $out .= '<td>';

            $out .= $link_render->link_no_attr($vr->get('users_show'), $pp->ary(),
                ['id' => $user_id], $user['name']);

            $out .= '</td>';

            $out .= '<td data-value="' . $hsh . '">';
            $out .= '<input type="number" name="amount[' . $user_id . ']" ';
            $out .= 'class="form-control" ';
            $out .= 'value="';
            $out .= $amount[$user_id] ?? '';
            $out .= '" ';
            $out .= 'min="0" ';
            $out .= 'data-letscode="' . $user['letscode'] . '" ';
            $out .= 'data-user-id="' . $user_id . '" ';
            $out .= 'data-balance="' . $user['saldo'] . '" ';
            $out .= 'data-minlimit="' . $user['minlimit'] . '"';
            $out .= '>';
            $out .= '</td>';

            $out .= '<td>';

            $balance = $user['saldo'];

            $minlimit = $user['minlimit'] === '' ? $system_minlimit : $user['minlimit'];
            $maxlimit = $user['maxlimit'] === '' ? $system_maxlimit : $user['maxlimit'];

            if (($minlimit !== '' && $balance < $minlimit)
                || ($maxlimit !== '' && $balance > $maxlimit))
            {
                $out .= '<span class="text-danger">' . $balance . '</span>';
            }
            else
            {
                $out .= $balance;
            }

            $out .= '</td>';

            $out .= '<td>' . $user['minlimit'] . '</td>';
            $out .= '<td>' . $user['maxlimit'] . '</td>';
            $out .= '<td>' . $user['postcode'] . '</td>';

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $out .= '<div class="panel-heading">';

        $out .= '<div class="form-group">';
        $out .= '<label for="total" class="control-label">Totaal';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="total" readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="to_letscode" class="control-label">';
        $out .= 'Aan Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="to_letscode" name="to_letscode" ';
        $out .= 'value="';
        $out .= $to_letscode;
        $out .= '" ';
        $out .= 'data-typeahead-source="from_letscode">';
        $out .= '</div>';
        $out .= '<p>Gebruik dit voor een "Veel naar één" transactie. ';
        $out .= 'Bijvoorbeeld, een ledenbijdrage. ';
        $out .= 'Alle ingevulde bedragen hierboven ';
        $out .= 'gaan naar dit Account.</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="description" class="control-label">';
        $out .= 'Omschrijving</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-pencil"></span></span>';
        $out .= '<input type="text" class="form-control" id="description" ';
        $out .= 'name="description" ';
        $out .= 'value="';
        $out .= $description;
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="mail_en" class="control-label">';
        $out .= '<input type="checkbox" id="mail_en" name="mail_en" value="1"';
        $out .= $mail_en ? ' checked="checked"' : '';
        $out .= '>';
        $out .= ' Verstuur notificatie mails</label>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label>';
        $out .= '<input type="checkbox" name="verify" ';
        $out .= 'value="1" required> ';
        $out .= 'Ik heb nagekeken dat de juiste ';
        $out .= 'bedragen en de juiste "Van" of "Aan" ';
        $out .= 'Account Code ingevuld zijn.';
        $out .= '</label>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('transactions', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Massa transactie uitvoeren" ';
        $out .= 'name="zend" class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<input type="hidden" value="';
        $out .= $transid;
        $out .= '" name="transid">';

        $out .= '</form>';

        $menu_service->set('mass_transaction');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
