<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Cnst\BulkCnst;
use App\Controller\Users\UsersListController;
use App\Form\Type\Filter\QTextSearchFilterType;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\AccountRepository;
use App\Service\AlertService;
use App\Service\AutoDeactivateService;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TransactionService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsMassController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/mass/{status}',
        name: 'transactions_mass',
        methods: ['GET', 'POST'],
        requirements: [
            'status'        => '%assert.account_status.user%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'status'        => 'active',
            'module'        => 'transactions',
            'sub_module'    => 'mass_transaction',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        string $status,
        AccountRepository $account_repository,
        LoggerInterface $logger,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        LinkRender $link_render,
        AccountRender $account_render,
        MailQueue $mail_queue,
        TypeaheadService $typeahead_service,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        AutoMinLimitService $autominlimit_service,
        AutoDeactivateService $auto_deactivate_service,
        TransactionService $transaction_service,
        ItemAccessService $item_access_service,
        PageParamsService $pp,
        SessionUserService $su
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

        $filter_form = $this->createForm(QTextSearchFilterType::class);
        $filter_form->handleRequest($request);

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


        /**
         * Fetch columns list
         */

         $sql_map = [
            'where'     => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['common'] = $sql_map;
        $sql['common']['where'][] = 'u.remote_schema is null';
        $sql['common']['where'][] = 'u.remote_email is null';

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $item_access_service, $pp);

        unset($status_def_ary['intersystem']);
        unset($status_def_ary['all']);

        if (!$new_users_enabled)
        {
            unset($status_def_ary['new']);
        }

        if (!$leaving_users_enabled)
        {
            unset($status_def_ary['leaving']);
        }

        $sql['status'] = $sql_map;

        foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
        {
            foreach ($def_sql_ary as $def_val)
            {
                if (is_array($def_val) && $st_def_key = 'where')
                {
                    $wh_or = '(';
                    $wh_or .= implode(' or ', $def_val);
                    $wh_or .= ')';
                    $sql['status'][$st_def_key][] = $wh_or;
                    continue;
                }

                $sql['status'][$st_def_key][] = $def_val;
            }
        }

        $params = ['status'	=> $status];

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));
        $sql_params = array_merge(...array_column($sql, 'params'));
        $sql_types = array_merge(...array_column($sql, 'types'));

        $query = 'select u.*
            from ' . $pp->schema() . '.users u
            where ' . $sql_where . '
            order by u.code asc';

        $users = [];

        $res = $db->executeQuery($query, $sql_params, $sql_types);

        while ($row = $res->fetchAssociative())
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

        $amount = $request->request->all('amount');
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

                    $auto_deactivate_service->process((int) $t['id_from'], $pp->schema());
                    $auto_deactivate_service->process((int) $t['id_to'], $pp->schema());
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
                        'to' 		=> [
                            ...$mail_addr_system_service->get_admin($pp->schema()),
                            ...$mail_addr_user_service->get_active($su->id(), $pp->schema()),
                            ...$mail_addr_user_service->get_active($one_uid, $pp->schema())
                        ],
                        'template'	=> $mail_template,
                        'vars'		=> $vars,
                    ], 8000);

                    $alert_service->success('Notificatie mails verzonden.');
                }

                return $this->redirectToRoute('transactions_mass', $pp->ary());
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

        $hfr = '<div class="panel panel-warning">';
        $hfr .= '<div class="panel-heading">';
        $hfr .= '<button class="btn btn-default btn-lg" ';
        $hfr .= 'title="Toon invul-hulp" data-toggle="collapse" ';
        $hfr .= 'data-target="#help" type="button">';
        $hfr .= '<i class="fa fa-question"></i>';
        $hfr .= ' Invul-hulp</button>';
        $hfr .= '</div>';
        $hfr .= '<div class="panel-heading collapse" id="help">';

        $hfr .= '<p>Met deze invul-hulp kan je snel alle ';
        $hfr .= 'bedragen van de massa-transactie invullen. ';
        $hfr .= 'De bedragen kan je nadien nog individueel ';
        $hfr .= 'aanpassen alvorens de massa transactie uit te voeren. ';
        $hfr .= '</p>';

        $hfr .= '<form class="form" data-fill-in ';

        $hfr .= 'data-transactions-sum-in="';
        $hfr .= htmlspecialchars($link_render->context_path('transactions_sum_in',
            $pp->ary(), ['days' => 365]));
        $hfr .= '" ';

        $hfr .= 'data-transactions-sum-out="';
        $hfr .= htmlspecialchars($link_render->context_path('transactions_sum_out',
            $pp->ary(), ['days' => 365]));
        $hfr .= '" ';

        $hfr .= 'data-weighted-balances="';
        $hfr .= htmlspecialchars($link_render->context_path('weighted_balances',
            $pp->ary(), ['days' => 365]));
        $hfr .= '"';

        $hfr .= '>';

        $hfr .= '<div class="pan-sub bg-warning">';

        $hfr .= '<div class="form-group">';
        $hfr .= '<label for="fixed" class="control-label">';
        $hfr .= 'Vast bedrag</label>';
        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">';
        $hfr .= $currency;
        $hfr .= '</span>';
        $hfr .= '<input type="number" class="form-control margin-bottom" id="fixed" ';
        $hfr .= 'min="0">';
        $hfr .= '</div>';
        $hfr .= '</div>';

        $hfr .= '</div>';

        /**/
        $hfr .= '<div class="pan-sub bg-warning">';

        $hfr .= '<h4>Variabel deel</h4>';

        $hfr .= '<div class="form-group">';
        $hfr .= '<label for="fixed" class="control-label">';
        $hfr .= 'Over periode</label>';
        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">';
        $hfr .= 'dagen</span>';
        $hfr .= '<input type="number" ';
        $hfr .= 'class="form-control margin-bottom" id="var_days" ';
        $hfr .= 'min="0">';
        $hfr .= '</div>';
        $hfr .= '</div>';

        //
        $hfr .= '<div class="form-group">';
        $hfr .= '<label for="var_balance" class="control-label">';
        $hfr .= 'Promille op saldo</label>';
        $hfr .= '<div class="row">';
        $hfr .= '<div class="col-sm-6">';

        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">&permil;</span>';
        $hfr .= '<input type="number" ';
        $hfr .= 'class="form-control margin-bottom" id="var_balance">';
        $hfr .= '</div>';
        $hfr .= '<p>Berekend op gewogen gemiddelde van saldo. ';
        $hfr .= 'Kan ook negatief zijn!</p>';
        $hfr .= '</div>';

        $hfr .= '<div class="col-sm-6">';
        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">';
        $hfr .= $currency;
        $hfr .= ': basis';
        $hfr .= '</span>';
        $hfr .= '<input type="number" class="form-control" id="var_base">';
        $hfr .= '</div>';
        $hfr .= '<p>De basis waartegenover berekend wordt. ';
        $hfr .= 'Kan ook afwijkend van nul zijn.</p>';
        $hfr .= '</div>';
        $hfr .= '</div>';
        $hfr .= '</div>';

        //
        $hfr .= '<div class="form-group">';
        $hfr .= '<label for="var_trans_in" class="control-label">';
        $hfr .= 'Promille op transacties in</label>';
        $hfr .= '<div class="row">';
        $hfr .= '<div class="col-sm-6">';
        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">&permil;</span>';
        $hfr .= '<input type="number" class="form-control" id="var_trans_in">';
        $hfr .= '</div>';
        $hfr .= '</div>';

        $hfr .= '<div class="col-sm-6">';
        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">';
        $hfr .= 'excl. ';
        $hfr .= '<i class="fa fa-user"></i>';
        $hfr .= '</span>';
        $hfr .= '<input type="text" class="form-control" ';
        $hfr .= 'id="var_ex_code_in" ';
        $hfr .= 'placeholder="Account Codes">';
        $hfr .= '</div>';
        $hfr .= '<p>Exclusief tegenpartijen: ';
        $hfr .= 'Account Codes gescheiden door komma\'s</p>';
        $hfr .= '</div>';
        $hfr .= '</div>';
        $hfr .= '</div>';

        //
        $hfr .= '<div class="form-group">';
        $hfr .= '<label for="var_trans_out" class="control-label">';
        $hfr .= 'Promille op transacties uit</label>';
        $hfr .= '<div class="row">';
        $hfr .= '<div class="col-sm-6">';
        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">&permil;</span>';
        $hfr .= '<input type="number" class="form-control" id="var_trans_out">';
        $hfr .= '</div>';
        $hfr .= '</div>';

        $hfr .= '<div class="col-sm-6">';
        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">';
        $hfr .= 'excl. ';
        $hfr .= '<i class="fa fa-user"></i>';
        $hfr .= '</span>';
        $hfr .= '<input type="text" class="form-control" ';
        $hfr .= 'id="var_ex_code_out" ';
        $hfr .= 'placeholder="Account Codes">';
        $hfr .= '</div>';
        $hfr .= '<p>Exclusief tegenpartijen: ';
        $hfr .= 'Account Codes gescheiden door komma\'s</p>';
        $hfr .= '</div>';
        $hfr .= '</div>';
        $hfr .= '</div>';

        //
        $hfr .= '<div class="form-group">';
        $hfr .= '<label for="var_minimum" class="control-label">';
        $hfr .= 'Minimum - maximum</label>';
        $hfr .= '<div class="row">';
        $hfr .= '<div class="col-sm-6">';

        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">';
        $hfr .= $currency;
        $hfr .= ': min';
        $hfr .= '</span>';

        $hfr .= '<input type="number" ';
        $hfr .= 'class="form-control margin-bottom" id="var_min">';
        $hfr .= '</div>';
        $hfr .= '</div>';

        $hfr .= '<div class="col-sm-6">';
        $hfr .= '<div class="input-group">';
        $hfr .= '<span class="input-group-addon">';
        $hfr .= $currency;
        $hfr .= ': max';
        $hfr .= '</span>';
        $hfr .= '<input type="number" class="form-control" id="var_max">';
        $hfr .= '</div>';
        $hfr .= '</div>';
        $hfr .= '</div>';
        $hfr .= '</div>';

        $hfr .= '</div>';

        if ($limits_enabled)
        {
            $hfr .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'        => 'respect_minlimit',
                '%label%'       => 'Respecteer minimum limieten',
                '%attr%'        => ' checked',
            ]);

            if (isset($system_min_limit) || isset($system_max_limit))
            {
                $hfr .= '<ul>';

                if (isset($system_min_limit))
                {
                    $hfr .= '<li>Minimum Systeemslimiet: ';
                    $hfr .= '<span class="label label-default">';
                    $hfr .= $system_min_limit;
                    $hfr .= '</span> ';
                    $hfr .= $currency;
                    $hfr .= '</li>';
                }

                if (isset($system_max_limit))
                {
                    $hfr .= '<li>Maximum Systeemslimiet: ';
                    $hfr .= '<span class="label label-default">';
                    $hfr .= $system_max_limit;
                    $hfr .= '</span> ';
                    $hfr .= $currency;
                    $hfr .= '</li>';
                }

                $hfr .= '<li>De Systeemslimieten gelden voor alle Accounts behalve de ';
                $hfr .= 'Accounts waarbij individuele limieten ingesteld zijn.</li>';

                $hfr .= '</ul>';
            }
        }


        if ($new_users_enabled)
        {
            $hfr .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => 'omit_new',
                '%label%'   => 'Sla <span class="bg-success text-success">instappers</span> over.',
            ]);
        }

        if ($leaving_users_enabled)
        {
            $hfr .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => 'omit_leaving',
                '%label%'   => 'Sla <span class="bg-danger text-danger">uitstappers</span> over.',
            ]);
        }

        $hfr .= '<button class="btn btn-default btn-lg" id="fill-in">';
        $hfr .= 'Vul in</button>';

        $hfr .= '</form>';

        $hfr .= '</div>';
        $hfr .= '</div>';

        /****
         * TABS
         */

        $out = '<ul class="nav nav-tabs" id="nav-tabs">';

        $nav_params = $params;

        foreach ($status_def_ary as $k => $tab)
        {
            $nav_params['status'] = $k;

            $out .= '<li';
            $out .= $params['status'] === $k ? ' class="active"' : '';
            $out .= '>';

            $out .= '<a href="';
            $out .= $link_render->context_path('transactions_mass',
                $pp->ary(), $nav_params);
            $out .= '"';

            if (isset($tab['cl']))
            {
                $out .= ' class="bg-';
                $out .= $tab['cl'];
                $out .= '"';
            }

            $out .= '>';
            $out .= $tab['lbl'];
            $out .= '</a>';
            $out .= '</li>';
         }

         $out .= '</ul>';

        /**
         *
         */

        $out .= '<form method="post" autocomplete="off">';

        $out .= '<input type="hidden" value="" id="combined-filter">';

        $out .= '<input type="hidden" value="" ';
        $out .= 'name="selected_users" id="selected_users">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $data_typeahead = $typeahead_service->ini($pp)
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'pre-active'])
            ->add('accounts', ['status' => 'post-active'])
            ->str([
                'filter'        => 'accounts',
                'new_users_days'        => $new_users_days,
                'show_new_status'       => $new_users_enabled,
                'show_leaving_status'   => $leaving_users_enabled,
            ]);

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
        $out .=  $data_typeahead;
        $out .= '">';

        $out .= '</div>';
        $out .= '<p>Vul dit enkel in bij een "Eén naar veel" transactie.';
        $out .= 'Alle ingevulde bedragen hieronder ';
        $out .= 'worden van dit Account gehaald.</p>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover panel-body footable" ';
        $out .= 'data-filter="#q" data-filter-minimum="1" ';
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
            $is_remote = isset($user['remote_schema']) || isset($user['remote_email']);
            $is_active = $user['is_active'];
            $is_leaving = $user['is_leaving'];
            $post_active = isset($user['activated_at']);
            $is_new = false;
            $is_status_new = false;
            $is_status_leaving = false;
            if ($post_active)
            {
                if ($new_user_treshold->getTimestamp() < strtotime($user['activated_at'] . ' UTC'))
                {
                    $is_new = true;
                }
            }

            $row_class = null;

            if ($is_active)
            {
                if ($is_remote)
                {
                    $row_class = 'warning';
                }
                else if ($is_leaving && $leaving_users_enabled)
                {
                    $row_class = 'danger';
                    $is_status_leaving = true;
                }
                else if ($is_new && $new_users_enabled)
                {
                    $row_class = 'success';
                    $is_status_new = true;
                }
            }
            else if ($post_active)
            {
                $row_class = 'inactive';
            }
            else
            {
                $row_class = 'info';
            }

            $out .= '<tr';

            if (isset($row_class))
            {
                $out .= ' class="';
                $out .= $row_class;
                $out .= '"';
            }

            $out .= ' data-user-id="';
            $out .= $user_id;
            $out .= '">';

            $out .= '<td>';

            $out .= $account_render->link($user_id, $pp->ary());

            $out .= '</td>';

            $out .= '<td>';
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

            if ($is_status_new)
            {
                $out .= ' data-new-account';
            }

            if ($is_status_leaving)
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
                $out .= '<td>';
                $out .= $user['min_limit'] ?? '';
                $out .= '</td>';
                $out .= '<td>';
                $out .= $user['max_limit'] ?? '';
                $out .= '</td>';
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
        $out .= 'data-typeahead="';
        $out .= $data_typeahead;
        $out .= '">';
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

        return $this->render('transactions/transactions_mass.html.twig', [
            'content'       => $out,
            'help_form_raw' => $hfr,
            'filter_form'   => $filter_form->createView(),
        ]);
    }
}
