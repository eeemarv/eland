<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Cnst\BulkCnst;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\AccountRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TransactionService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TransactionsMassController extends AbstractController
{
    const STATUS_RENDER = [
        'active'	=> [
            'lbl'	=> 'Actief',
            'st'	=> 1,
            'hsh'	=> '58d267',
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
    ];

    #[Route(
        '/{system}/{role_short}/transactions/mass',
        name: 'transactions_mass',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'transactions',
            'sub_module'    => 'mass_transaction',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        AccountRepository $account_repository,
        LoggerInterface $logger,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        ItemAccessService $item_access_service,
        ConfigService $config_service,
        MenuService $menu_service,
        LinkRender $link_render,
        AccountRender $account_render,
        MailQueue $mail_queue,
        TypeaheadService $typeahead_service,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        AutoMinLimitService $autominlimit_service,
        TransactionService $transaction_service,
        PageParamsService $pp,
        SessionUserService $su,
        AssetsService $assets_service
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        if (!$config_service->get_bool('transactions.mass.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Submodule mass-transaction not enabled.');
        }

        $errors = [];

        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $system_min_limit = $config_service->get_int('accounts.limits.global.min', $pp->schema());
        $system_max_limit = $config_service->get_int('accounts.limits.global.max', $pp->schema());
        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());
        $new_users_days = $config_service->get_int('users.new.days', $pp->schema());
        $new_users_enabled = $config_service->get_bool('users.new.enabled', $pp->schema());
        $leaving_users_enabled = $config_service->get_bool('users.leaving.enabled', $pp->schema());
        $limits_enabled = $config_service->get_bool('accounts.limits.enabled', $pp->schema());

        $show_new_status = $new_users_enabled;

        if ($show_new_status)
        {
            $new_users_access = $config_service->get_str('users.new.access', $pp->schema());
            $show_new_status = $item_access_service->is_visible($new_users_access);
        }

        $show_leaving_status = $leaving_users_enabled;

        if ($show_leaving_status)
        {
            $leaving_users_access = $config_service->get_str('users.leaving.access', $pp->schema());
            $show_leaving_status = $item_access_service->is_visible($leaving_users_access);
        }

        $q = $request->get('q', '');
        $hsh = $request->get('hsh', '58d267');

        $selected_users = $request->request->get('selected_users', '');
        $selected_users = ltrim($selected_users, '.');
        $selected_users = explode('.', $selected_users);
        $selected_users = array_combine($selected_users, $selected_users);

        $balance_ary = $account_repository->get_balance_ary($pp->schema());

        if ($limits_enabled)
        {
            $min_limit_ary = $account_repository->get_min_limit_ary($pp->schema());
            $max_limit_ary = $account_repository->get_max_limit_ary($pp->schema());
        }

        $users = [];

        $stmt = $db->prepare(
            'select id, name, code,
                role, status, adate
            from ' . $pp->schema() . '.users
            where status IN (0, 1, 2, 5, 6)
            order by code');

        $stmt->execute();

        while ($row = $stmt->fetch())
        {
            $row['balance'] = $balance_ary[$row['id']] ?? 0;

            if (isset($min_limit_ary[$row['id']]))
            {
                $row['min_limit'] = $min_limit_ary[$row['id']];
            }

            if (isset($max_limit_ary[$row['id']]))
            {
                $row['max_limit'] = $max_limit_ary[$row['id']];
            }

            $users[$row['id']] = $row;
        }

        $to_code = trim($request->request->get('to_code', ''));

        if ($to_code !== '')
        {
            [$to_code] = explode(' ', $to_code);
        }

        $from_code = trim($request->request->get('from_code', ''));

        if ($from_code !== '')
        {
            [$from_code] = explode(' ', $from_code);
        }

        $amount = $request->request->get('amount', []);
        $description = trim($request->request->get('description', ''));
        $mail_en = $request->request->has('mail_en');

        if ($request->isMethod('POST'))
        {
            if (!$request->request->has('verify'))
            {
                $errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!$description)
            {
                $errors[] = 'Vul een omschrijving in.';
            }

            if ($to_code && $from_code)
            {
                $errors[] = '\'Van Account Code\' en \'Aan Account Code\' kunnen niet beide ingevuld worden.';
            }
            else if (!($to_code || $from_code))
            {
                $errors[] = '\'Van Account Code\' OF \'Aan Account Code\' moet ingevuld worden.';
            }
            else
            {
                $to_one = $to_code ? true : false;
                $code = $to_one ? $to_code : $from_code;

                $one_uid = $db->fetchOne('select id
                    from ' . $pp->schema() . '.users
                    where code = ?',
                    [$code], [\PDO::PARAM_STR]);

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

            if (count($errors))
            {
                $alert_service->error($errors);
            }
            else
            {
                $transactions = [];

                $db->beginTransaction();

                $alert_success = $log_many = '';
                $total_amount = 0;

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

                    $amo = (int) $amo;

                    $many_user = $users[$many_uid];
                    $to_id = $to_one ? $one_uid : $many_uid;
                    $from_id = $to_one ? $many_uid : $one_uid;
                    $from_user = $users[$from_id];
                    $to_user = $users[$to_id];

                    $alert_success .= 'Transactie van gebruiker ' . $from_user['code'] . ' ' . $from_user['name'];
                    $alert_success .= ' naar ' . $to_user['code'] . ' ' . $to_user['name'];
                    $alert_success .= '  met bedrag ' . $amo .' ';
                    $alert_success .= $currency;
                    $alert_success .= ' uitgevoerd.<br>';

                    $log_many .= $many_user['code'] . ' ' . $many_user['name'] . '(' . $amo . '), ';

                    $transaction = [
                        'id_to' 		=> $to_id,
                        'id_from' 		=> $from_id,
                        'amount' 		=> $amo,
                        'description' 	=> $description,
                        'transid'		=> $transaction_service->generate_transid($su->id(), $pp->system()),
                    ];

                    if (!$su->is_master())
                    {
                        $transaction['created_by'] = $su->id();
                    }

                    $db->insert($pp->schema() . '.transactions', $transaction);
                    $transaction['id'] = $db->lastInsertId($pp->schema() . '.transactions_id_seq');
                    $account_repository->update_balance($to_id, $amo, $pp->schema());
                    $account_repository->update_balance($from_id, -$amo, $pp->schema());

                    $total_amount += $amo;

                    $transactions[] = $transaction;
                }

                $db->commit();

                foreach($transactions as $t)
                {
                    $autominlimit_service->process(
                        (int) $t['id_from'],
                        (int) $t['id_to'],
                        (int) $t['amount'],
                        $pp->schema()
                    );
                }

                $alert_success .= 'Totaal: ' . $total_amount . ' ';
                $alert_success .= $currency;
                $alert_service->success($alert_success);

                $log_one = $users[$one_uid]['code'] . ' ';
                $log_one .= $users[$one_uid]['name'];
                $log_one .= '(Total amount: ' . $total_amount . ' ';
                $log_one .= $currency;
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

                $link_render->redirect('transactions_mass', $pp->ary(), []);
            }
        }

        if ($to_code)
        {
            if ($to_name = $db->fetchOne('select name
                from ' . $pp->schema() . '.users
                where code = ?',
                [$to_code], [\PDO::PARAM_STR]))
            {
                $to_code .= ' ' . $to_name;
            }
        }

        if ($from_code)
        {
            if ($from_name = $db->fetchOne('select name
                from ' . $pp->schema() . '.users
                where code = ?',
                [$from_code], [\PDO::PARAM_STR]))
            {
                $from_code .= ' ' . $from_name;
            }
        }

        $assets_service->add([
            'mass_transaction.js',
            'combined_filter.js',
        ]);

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

        $out .= '<form class="form" data-fill-in ';

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
        $out .= $currency;
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
        $out .= $currency;
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
        $out .= $currency;
        $out .= ': min';
        $out .= '</span>';

        $out .= '<input type="number" ';
        $out .= 'class="form-control margin-bottom" id="var_min">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $currency;
        $out .= ': max';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="var_max">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        if ($limits_enabled)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'        => 'respect_minlimit',
                '%label%'       => 'Respecteer minimum limieten',
                '%attr%'        => ' checked',
            ]);

            if (isset($system_min_limit) || isset($system_max_limit))
            {
                $out .= '<ul>';

                if (isset($system_min_limit))
                {
                    $out .= '<li>Minimum Systeemslimiet: ';
                    $out .= '<span class="label label-default">';
                    $out .= $system_min_limit;
                    $out .= '</span> ';
                    $out .= $currency;
                    $out .= '</li>';
                }

                if (isset($system_max_limit))
                {
                    $out .= '<li>Maximum Systeemslimiet: ';
                    $out .= '<span class="label label-default">';
                    $out .= $system_max_limit;
                    $out .= '</span> ';
                    $out .= $currency;
                    $out .= '</li>';
                }

                $out .= '<li>De Systeemslimieten gelden voor alle Accounts behalve de ';
                $out .= 'Accounts waarbij individuele limieten ingesteld zijn.</li>';

                $out .= '</ul>';
            }
        }


        if ($new_users_enabled)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => 'omit_new',
                '%label%'   => 'Sla <span class="bg-success text-success">instappers</span> over.',
            ]);
        }

        if ($leaving_users_enabled)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => 'omit_leaving',
                '%label%'   => 'Sla <span class="bg-danger text-danger">uitstappers</span> over.',
            ]);
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
            if ($k === 'new' && !$new_users_enabled)
            {
                continue;
            }

            if ($k === 'leaving' && !$leaving_users_enabled)
            {
                continue;
            }

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
        $out .= '<label for="from_code" class="control-label">';
        $out .= 'Van Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="from_code" name="from_code" ';
        $out .= 'value="';
        $out .= $from_code;
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
                'new_users_days'        => $new_users_days,
                'show_new_status'       => $show_new_status,
                'show_leaving_status'   => $show_leaving_status,
            ]);
        $out .= '">';

        $out .= '</div>';
        $out .= '<p>Vul dit enkel in bij een "Eén naar veel" transactie.';
        $out .= 'Alle ingevulde bedragen hieronder ';
        $out .= 'worden van dit Account gehaald.</p>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover panel-body footable" ';
        $out .= 'data-filter="#combined-filter" data-filter-minimum="1" ';
        $out .= 'data-minlimit="';
        $out .= $system_min_limit;
        $out .= '" ';
        $out .= 'data-maxlimit="';
        $out .= $system_max_limit;
        $out .= '"';
        $out .= '>';
        $out .= '<thead>';

        $out .= '<tr>';
        $out .= '<th data-sort-initial="true">Account</th>';
        $out .= '<th data-sort-ignore="true">Bedrag</th>';
        $out .= '<th data-hide="phone">Saldo</th>';

        if ($limits_enabled)
        {
            $out .= '<th data-hide="phone">Min.limit</th>';
            $out .= '<th data-hide="phone">Max.limit</th>';
        }

        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        foreach($users as $user_id => $user)
        {
            $status_key = self::STATUS[$user['status']];

            if (isset($user['adate'])
                && $status_key === 'active'
                && $new_users_enabled
                && $new_user_treshold->getTimestamp() < strtotime($user['adate'] . ' UTC')
            )
            {
                $status_key = 'new';
            }

            if ($status_key === 'leaving'
                && !$leaving_users_enabled
            )
            {
                $status_key = 'active';
            }

            $hsh = self::STATUS_RENDER[$status_key]['hsh'] ?: '';
            $hsh .= $status_key == 'leaving' || $status_key == 'new' ? self::STATUS_RENDER['active']['hsh'] : '';

            $class = isset(self::STATUS_RENDER[$status_key]['cl']) ? ' class="' . self::STATUS_RENDER[$status_key]['cl'] . '"' : '';

            $out .= '<tr' . $class . ' data-user-id="' . $user_id . '">';

            $out .= '<td>';

            $out .= $account_render->link($user_id, $pp->ary());

            $out .= '</td>';

            $out .= '<td data-value="' . $hsh . '">';
            $out .= '<input type="number" name="amount[' . $user_id . ']" ';
            $out .= 'class="form-control" ';
            $out .= 'value="';
            $out .= $amount[$user_id] ?? '';
            $out .= '" ';
            $out .= 'min="0" ';
            $out .= 'data-code="' . $user['code'] . '" ';
            $out .= 'data-user-id="' . $user_id . '" ';
            $out .= 'data-balance="' . $user['balance'] . '" ';
            $out .= 'data-minlimit="' . ($user['min_limit'] ?? '') . '"';

            if ($status_key === 'new')
            {
                $out .= ' data-new-account';
            }

            if ($status_key === 'leaving')
            {
                $out .= ' data-leaving-account';
            }

            $out .= '>';
            $out .= '</td>';

            $out .= '<td>';

            $balance = $user['balance'];

            $minlimit = $user['min_limit'] ?? $system_min_limit;
            $maxlimit = $user['max_limit'] ?? $system_max_limit;

            if ((isset($minlimit) && $balance < $minlimit)
                || (isset($maxlimit) && $balance > $maxlimit))
            {
                $out .= '<span class="text-danger">' . $balance . '</span>';
            }
            else
            {
                $out .= $balance;
            }

            $out .= '</td>';

            if ($limits_enabled)
            {
                $out .= '<td>' . ($user['min_limit'] ?? '') . '</td>';
                $out .= '<td>' . ($user['max_limit'] ?? '') . '</td>';
            }

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
        $out .= $currency;
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="total" readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="to_code" class="control-label">';
        $out .= 'Aan Account Code';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="to_code" name="to_code" ';
        $out .= 'value="';
        $out .= $to_code;
        $out .= '" ';
        $out .= 'data-typeahead-source="from_code">';
        $out .= '</div>';
        $out .= '<p>Vul dit enkel in bij een "Veel naar één" transactie. ';
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

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'        => 'mail_en',
            '%label%'       => 'Verstuur notificatie E-mails',
            '%attr%'        => $mail_en ? ' checked' : '',
        ]);

        $lbl_verify = 'Ik heb nagekeken dat de juiste ';
        $lbl_verify .= 'bedragen en de juiste "Van" of "Aan" ';
        $lbl_verify .= 'Account Code ingevuld zijn.';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'        => 'verify',
            '%label%'       => $lbl_verify,
            '%attr%'        => ' required',
        ]);

        $out .= $link_render->btn_cancel('transactions', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Massa transactie uitvoeren" ';
        $out .= 'name="zend" class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '</form>';

        $menu_service->set('transactions_mass');

        return $this->render('transactions/transactions_mass.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
