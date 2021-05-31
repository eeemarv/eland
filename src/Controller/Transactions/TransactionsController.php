<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Cnst\BulkCnst;
use App\Cnst\MessageTypeCnst;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Render\PaginationRender;
use App\Render\SelectRender;
use App\Repository\AccountRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TransactionsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions',
        name: 'transactions',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'is_self'       => false,
            'module'        => 'transactions',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/transactions/self',
        name: 'transactions_self',
        methods: ['GET', 'POST'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'is_self'       => true,
            'module'        => 'transactions',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        bool $is_self,
        AccountRepository $account_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        FormTokenService $form_token_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        PaginationRender $pagination_render,
        SelectRender $select_render,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        if (!$request->isMethod('GET') && !$pp->is_admin())
        {
            throw new BadRequestException('POST not allowed');
        }

        $intersystem_account_schemas = $intersystems_service->get_eland_accounts_schemas($pp->schema());
        $su_intersystem_ary = $intersystems_service->get_eland($su->schema());
        $su_intersystem_ary[$su->schema()] = true;

        $service_stuff_enabled = $config_service->get_bool('transactions.fields.service_stuff.enabled', $pp->schema());
        $bulk_actions_enabled = $service_stuff_enabled;

        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        if ($is_self)
        {
            $filter['uid'] = $su->id();
        }

        $vr_route = 'transactions' . ($is_self ? '_self' : '');

        $selected_transactions = $request->request->get('sel', []);
        $bulk_field = $request->request->get('bulk_field', []);
        $bulk_verify = $request->request->get('bulk_verify', []);
        $bulk_submit = $request->request->get('bulk_submit', []);

        if ($request->isMethod('POST')
            && $pp->is_admin()
            && count($bulk_submit)
            && $bulk_actions_enabled)
        {
            $errors = [];

            if (count($bulk_submit) > 1)
            {
                throw new BadRequestHttpException('Invalid form. More than one submit.');
            }

            if (count($bulk_field) > 1)
            {
                throw new BadRequestHttpException('Invalid form. More than one bulk field.');
            }

            if (count($bulk_verify) > 1)
            {
                throw new BadRequestHttpException('Invalid form. More than one bulk verify checkbox.');
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($selected_transactions))
            {
                $errors[] = 'Selecteer ten minste één transactie voor deze actie.';
            }

            if (count($bulk_verify) !== 1)
            {
                $errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
            }

            $bulk_submit_action = array_key_first($bulk_submit);
            $bulk_verify_action = array_key_first($bulk_verify);
            $bulk_field_action = array_key_first($bulk_field);

            if (isset($bulk_verify_action)
                && $bulk_verify_action !== $bulk_submit_action)
            {
                throw new BadRequestHttpException('Invalid form. Not matching verify checkbox to bulk action.');
            }

            if (isset($bulk_field_action)
                && $bulk_field_action !== $bulk_submit_action)
            {
                throw new BadRequestHttpException('Invalid form. Not matching field to bulk action.');
            }

            if (!isset($bulk_field_action))
            {
                throw new BadRequestHttpException('Invalid form. Missing value.');
            }

            $bulk_field_value = $bulk_field[$bulk_field_action];

            if (!isset($bulk_field_value) || !$bulk_field_value)
            {
                $errors[] = 'Bulk actie waarde-veld niet ingevuld.';
            }

            if (!count($errors))
            {
                $update_transactions_ary  = [];

                $stmt = $db->executeQuery('select *
                    from ' . $pp->schema() . '.transactions
                    where id in (?)',
                    [array_keys($selected_transactions)],
                    [Db::PARAM_INT_ARRAY]);

                while ($row = $stmt->fetch())
                {
                    if ($row['real_from'] || $row['real_to'])
                    {
                        if (isset($intersystem_account_schemas[$row['id_from']]))
                        {
                            $row['inter_schema'] = $intersystem_account_schemas[$row['id_from']];

                        }
                        else if (isset($intersystem_account_schemas[$row['id_to']]))
                        {
                            $row['inter_schema'] = $intersystem_account_schemas[$row['id_to']];
                        }
                    }

                    $update_transactions_ary[$row['id']] = $row;
                }
            }

            if ($bulk_submit_action === 'service_stuff' && !count($errors))
            {
                if (!$service_stuff_enabled)
                {
                    throw new BadRequestHttpException('Service/stuff sub-module not enabled.');
                }

                if (!in_array($bulk_field_value, ['service', 'stuff', 'null-service-stuff']))
                {
                    throw new BadRequestHttpException('Unvalid value: ' . $bulk_field_value);
                }

                if ($bulk_field_value === 'null-service-stuff')
                {
                    $bulk_field_value = null;
                }

                $update_ary = [
                    'service_stuff'   => $bulk_field_value,
                ];

                foreach ($update_transactions_ary as $id => $row)
                {
                    $db->update($pp->schema() . '.transactions',
                        $update_ary, ['id' => $id]);

                    if (isset($row['inter_schema']))
                    {
                        $db->update($row['inter_schema'] . '.transactions',
                        $update_ary, ['transid' => $row['transid']]);
                    }
                }

                if (count($selected_transactions) > 1)
                {
                    $alert_service->success('De transacties zijn aangepast.');
                }
                else
                {
                    $alert_service->success('De transactie is aangepast.');
                }

                $link_render->redirect($vr_route, $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        if (isset($filter['uid']))
        {
            $filter['uid'] = (int) $filter['uid'];
            $balance = $account_repository->get_balance((int) $filter['uid'], $pp->schema());
        }

        $is_owner = isset($filter['uid'])
            && $su->is_owner((int) $filter['uid']);

        $params = [
            's'	=> [
                'orderby'	=> $sort['orderby'] ?? 'created_at',
                'asc'		=> $sort['asc'] ?? 0,
            ],
            'p'	=> [
                'start'		=> $pag['start'] ?? 0,
                'limit'		=> $pag['limit'] ?? 25,
            ],
        ];

        $sql_map = [
            'where'     => [],
            'where_or'  => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [
            'common'    => $sql_map,
        ];

        $sql['common']['where'][] = '1 = 1';

        $filter_uid = isset($filter['uid']) && $filter['uid'];

        if ($filter_uid)
        {
            $filter['fcode'] = $account_render->str((int) $filter['uid'], $pp->schema());
            $filter['tcode'] = $filter['fcode'];
            $filter['andor'] = 'or';
            $params['f']['uid'] = $filter['uid'];
        }

        $filter_q = isset($filter['q']) && $filter['q'] !== '';

        if ($filter_q)
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][] = 't.description ilike ?';
            $sql['q']['params'][] = '%' . $filter['q'] . '%';
            $sql['q']['types'][] = \PDO::PARAM_STR;
            $params['f']['q'] = $filter['q'];
        }

        $key_code_where_or = 'where';
        $key_code_where_or .= isset($filter['andor']) && $filter['andor'] === 'or' ? '_or' : '';
        $sql['code'] = $sql_map;

        $filter_fcode = isset($filter['fcode']) && $filter['fcode'];

        if ($filter_fcode)
        {
            [$fcode] = explode(' ', trim($filter['fcode']));
            $fcode = trim($fcode);

            $fuid = $db->fetchOne('select id
                from ' . $pp->schema() . '.users
                where code = ?', [$fcode], [\PDO::PARAM_STR]);

            if ($fuid)
            {
                $fuid_sql = 't.id_from ';
                $fuid_sql .= $filter['andor'] === 'nor' ? '<>' : '=';
                $fuid_sql .= ' ?';

                $sql['code'][$key_code_where_or][] = $fuid_sql;
                $sql['code']['params'][] = $fuid;
                $sql['code']['types'][] = \PDO::PARAM_STR;

                $fcode = $account_render->str($fuid, $pp->schema());
            }
            else if ($filter['andor'] !== 'nor')
            {
                $sql['code'][$key_code_where_or][] = '1 = 2';
            }

            $params['f']['fcode'] = $fcode;
        }

        $filter_tcode = isset($filter['tcode']) && $filter['tcode'];

        if ($filter_tcode)
        {
            [$tcode] = explode(' ', trim($filter['tcode']));

            $tuid = $db->fetchOne('select id
                from ' . $pp->schema() . '.users
                where code = ?',
                [$tcode], [\PDO::PARAM_STR]);

            if ($tuid)
            {
                $tuid_sql = 't.id_to ';
                $tuid_sql .= $filter['andor'] === 'nor' ? '<>' : '=';
                $tuid_sql .= ' ?';
                $sql['code'][$key_code_where_or][] = $tuid_sql;
                $sql['code']['params'][] = $tuid;
                $sql['code']['types'][] = \PDO::PARAM_STR;

                $tcode = $account_render->str($tuid, $pp->schema());
            }
            else if ($filter['andor'] !== 'nor')
            {
                $sql['code'][$key_code_where_or][] = '1 = 2';
            }

            $params['f']['tcode'] = $tcode;
        }

        if ($filter_fcode || $filter_tcode)
        {
            $params['f']['andor'] = $filter['andor'];
        }

        if (count($sql['code']['where_or']))
        {
            $sql['code']['where'] = [' ( ' . implode(' or ', $sql['code']['where_or']) . ' ) '];
        }

        $filter_fdate = isset($filter['fdate']) && $filter['fdate'];

        if ($filter_fdate)
        {
            $fdate_sql = $date_format_service->reverse($filter['fdate'], $pp->schema());

            if ($fdate_sql === '')
            {
                $alert_service->warning('De begindatum is fout geformateerd.');
            }
            else
            {
                $sql['fdate'] = $sql_map;

                $fdate_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($fdate_sql . ' UTC'));

                $sql['fdate']['where'][] = 't.created_at >= ?';
                $sql['fdate']['params'][] = $fdate_immutable;
                $sql['fdate']['types'][] = Types::DATETIME_IMMUTABLE;
                $params['f']['fdate'] = $fdate = $filter['fdate'];
            }
        }

        $filter_tdate = isset($filter['tdate']) && $filter['tdate'];

        if ($filter_tdate)
        {
            $tdate_sql = $date_format_service->reverse($filter['tdate'], $pp->schema());

            if ($tdate_sql === '')
            {
                $alert_service->warning('De einddatum is fout geformateerd.');
            }
            else
            {
                $sql['tdate'] = $sql_map;

                $tdate_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($tdate_sql . ' UTC'));

                $sql['tdate']['where'][] = 't.created_at <= ?';
                $sql['tdate']['params'][] = $tdate_immutable;
                $sql['tdate']['types'][] = Types::DATETIME_IMMUTABLE;
                $params['f']['tdate'] = $tdate = $filter['tdate'];
            }
        }

        $filter_service_stuff = $service_stuff_enabled
            && (isset($filter['service'])
                || isset($filter['stuff'])
                || isset($filter['null-service-stuff'])
            );

        if ($filter_service_stuff)
        {
            $sql['service_stuff'] = $sql_map;

            if (isset($filter['service']))
            {
                $sql['service_stuff']['where_or'][] = 't.service_stuff = \'service\'';
                $params['f']['service'] = '1';
            }

            if (isset($filter['stuff']))
            {
                $sql['service_stuff']['where_or'][] = 't.service_stuff = \'stuff\'';
                $params['f']['stuff'] = '1';
            }

            if (isset($filter['null-service-stuff']))
            {
                $sql['service_stuff']['where_or'][] = 't.service_stuff is null';
                $params['f']['null-service-stuff'] = '1';
            }

            if (count($sql['service_stuff']['where_or']))
            {
                $sql['service_stuff']['where'][] = '(' . implode(' or ', $sql['service_stuff']['where_or']) . ')';
            }
        }

        $sql['pagination'] = $sql_map;
        $sql['pagination']['params'][] = $params['p']['limit'];
        $sql['pagination']['types'][] = \PDO::PARAM_INT;
        $sql['pagination']['params'][] = $params['p']['start'];
        $sql['pagination']['types'][] = \PDO::PARAM_INT;

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));

        $query = 'select t.*
            from ' . $pp->schema() . '.transactions t
            where ' . $sql_where . '
            order by t.' . $params['s']['orderby'] . ' ';
        $query .= $params['s']['asc'] ? 'asc ' : 'desc ';
        $query .= 'limit ? offset ?';

        $stmt = $db->executeQuery($query,
            array_merge(...array_column($sql, 'params')),
            array_merge(...array_column($sql, 'types')));

        $transactions = [];
        $inter_fetch = [];

        while ($row = $stmt->fetch())
        {
            if ($row['real_from'] || $row['real_to'])
            {
                if (isset($intersystem_account_schemas[$row['id_from']]))
                {
                    $row['inter_schema'] = $intersystem_account_schemas[$row['id_from']];

                }
                else if (isset($intersystem_account_schemas[$row['id_to']]))
                {
                    $row['inter_schema'] = $intersystem_account_schemas[$row['id_to']];
                }

                if (isset($row['inter_schema']))
                {
                    $inter_fetch[$row['transid']] = $row['inter_schema'];
                }
            }

            $transactions[$row['transid']] = $row;
        }

        foreach ($inter_fetch as $transid => $inter_schema)
        {
            $inter_transaction = $db->fetchAssociative('select t.*
                from ' . $inter_schema . '.transactions t
                where t.transid = ?',
                [$transid], [\PDO::PARAM_STR]);

            if ($inter_transaction)
            {
                $transactions[$transid]['inter_transaction'] = $inter_transaction;
            }
        }

        $sql_omit_pagination = $sql;
        unset($sql_omit_pagination['pagination']);
        $sql_omit_pagination_where = implode(' and ', array_merge(...array_column($sql_omit_pagination, 'where')));

        $row = $db->fetchAssociative('select count(t.*), sum(t.amount)
            from ' . $pp->schema() . '.transactions t
            where ' . $sql_omit_pagination_where,
            array_merge(...array_column($sql_omit_pagination, 'params')),
            array_merge(...array_column($sql_omit_pagination, 'types')));

        $row_count = $row['count'];
        $amount_sum = $row['sum'];

        $count_ary = [
            'service'               => 0,
            'stuff'                 => 0,
            'null-service-stuff'    => 0,
        ];

        if ($service_stuff_enabled)
        {
            $sql_omit_service_stuff = $sql_omit_pagination;
            unset($sql_omit_service_stuff['service_stuff']);

            $sql_omit_service_stuff_where = implode(' and ', array_merge(...array_column($sql_omit_service_stuff, 'where')));

            $count_service_stuff_query = 'select count(t.*), t.service_stuff
                from ' . $pp->schema() . '.transactions t
                where ' . $sql_omit_service_stuff_where . '
                group by t.service_stuff';

            $stmt = $db->executeQuery($count_service_stuff_query,
                array_merge(...array_column($sql_omit_service_stuff, 'params')),
                array_merge(...array_column($sql_omit_service_stuff, 'types')));

            while($row = $stmt->fetch())
            {
                $count_ary[$row['service_stuff'] ?? 'null-service-stuff'] = $row['count'];
            }
        }

        $pagination_render->init($vr_route, $pp->ary(),
            $row_count, $params);

        $asc_preset_ary = [
            'asc'	=> 0,
            'fa' 	=> 'sort',
        ];

        $tableheader_ary = [
            'description' => array_merge($asc_preset_ary, [
                'lbl' => 'Omschrijving',
            ]),
            'amount' => array_merge($asc_preset_ary, [
                'lbl' => $config_service->get('currency', $pp->schema()),
            ]),
            'created_at'	=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'Tijdstip',
                'data_hide' => 'phone',
            ])
        ];

        if ($filter_uid)
        {
            $tableheader_ary['user'] = array_merge($asc_preset_ary, [
                'lbl'			=> 'Tegenpartij',
                'data_hide'		=> 'phone, tablet',
                'no_sort'		=> true,
            ]);
        }
        else
        {
            $tableheader_ary += [
                'from_user' => array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Van',
                    'data_hide'	=> 'phone, tablet',
                    'no_sort'	=> true,
                ]),
                'to_user' => array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Aan',
                    'data_hide'	=> 'phone, tablet',
                    'no_sort'	=> true,
                ]),
            ];
        }

        $tableheader_ary[$params['s']['orderby']]['asc']
            = $params['s']['asc'] ? 0 : 1;
        $tableheader_ary[$params['s']['orderby']]['fa']
            = $params['s']['asc'] ? 'sort-asc' : 'sort-desc';

        if ($filter_uid)
        {
            $user = $user_cache_service->get((int) $filter['uid'], $pp->schema());
            $user_str = $account_render->str($user['id'], $pp->schema());
        }

        if ($pp->is_admin() || $pp->is_user())
        {
            if ($filter_uid)
            {
                if ($user['status'] != 7)
                {
                    if ($is_owner)
                    {
                        $btn_top_render->add('transactions_add', $pp->ary(),
                            [], 'Transactie toevoegen');
                    }
                    else
                    {
                        $btn_top_render->add_trans('transactions_add', $pp->ary(),
                            ['tuid' => $user['id']],
                            'Transactie naar ' . $user_str);
                    }
                }
            }
            else
            {
                $btn_top_render->add('transactions_add', $pp->ary(),
                    [], 'Transactie toevoegen');
            }
        }

        if ($pp->is_admin())
        {
            if ($bulk_actions_enabled)
            {
                $btn_top_render->local('#bulk_actions', 'Bulk acties', 'envelope-o');
                $assets_service->add(['table_sel.js']);
            }

            $btn_nav_render->csv();
        }

        $filtered = !$filter_uid && (
            $filter_q
            || $filter_fcode
            || $filter_tcode
            || $filter_fdate
            || $filter_tdate
            || $filter_service_stuff
        );

        $template = 'transactions/transactions_';
        $template .= $filter_uid ? 'uid' : 'list';
        $template .= '.html.twig';

        $out = '';

        $assets_service->add(['datepicker']);

        $out .= '<div class="panel panel-info';
        $out .= $filtered ? '' : ' collapse';
        $out .= '" id="filter">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get" ';
        $out .= 'class="form-horizontal" ';
        $out .= 'action="';
        $out .= $link_render->context_path('transactions', $pp->ary(), []);
        $out .= '">';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-12">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" value="';
        $out .= $filter['q'] ?? '';
        $out .= '" name="f[q]" placeholder="Zoekterm">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="fcode_addon">Van ';
        $out .= '<span class="fa fa-user"></span></span>';

        $typeahead_service->ini($pp->ary())
            ->add('accounts', ['status' => 'active']);

        if (!$pp->is_guest())
        {
            $typeahead_service->add('accounts', ['status' => 'extern']);
        }

        if ($pp->is_admin())
        {
            $typeahead_service->add('accounts', ['status' => 'inactive']);
            $typeahead_service->add('accounts', ['status' => 'ip']);
            $typeahead_service->add('accounts', ['status' => 'im']);
        }

        $out .= '<input type="text" class="form-control" ';
        $out .= 'aria-describedby="fcode_addon" ';

        $out .= 'data-typeahead="';

        $out .= $typeahead_service->str([
            'filter'		=> 'accounts',
            'newuserdays'	=> $config_service->get('newuserdays', $pp->schema()),
        ]);

        $out .= '" ';

        $out .= 'name="f[fcode]" id="fcode" placeholder="Account Code" ';
        $out .= 'value="';
        $out .= $fcode ?? '';
        $out .= '">';

        $out .= '</div>';
        $out .= '</div>';

        $andor_options = [
            'and'	=> 'EN',
            'or'	=> 'OF',
            'nor'	=> 'NOCH',
        ];

        $out .= '<div class="col-sm-2">';
        $out .= '<select class="form-control margin-bottom" name="f[andor]">';
        $out .= $select_render->get_options($andor_options, $filter['andor'] ?? 'and');
        $out .= '</select>';
        $out .= '</div>';

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="tcode_addon">Naar ';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control margin-bottom" ';
        $out .= 'data-typeahead-source="fcode" ';
        $out .= 'placeholder="Account Code" ';
        $out .= 'aria-describedby="tcode_addon" ';
        $out .= 'name="f[tcode]" value="';
        $out .= $tcode ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<div class="row">';

        $date_col_width = $service_stuff_enabled ? '6' : '5';

        $out .= '<div class="col-sm-' . $date_col_width . '">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="fdate_addon">Vanaf ';
        $out .= '<span class="fa fa-calendar"></span></span>';
        $out .= '<input type="text" class="form-control margin-bottom" ';
        $out .= 'aria-describedby="fdate_addon" ';

        $out .= 'id="fdate" name="f[fdate]" ';
        $out .= 'value="';
        $out .= $fdate ?? '';
        $out .= '" ';
        $out .= 'data-provide="datepicker" ';
        $out .= 'data-date-format="';
        $out .= $date_format_service->datepicker_format($pp->schema());
        $out .= '" ';
        $out .= 'data-date-default-view-date="-1y" ';
        $out .= 'data-date-end-date="0d" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-immediate-updates="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'placeholder="';
        $out .= $date_format_service->datepicker_placeholder($pp->schema());
        $out .= '">';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-' . $date_col_width . '">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="tdate_addon">Tot ';
        $out .= '<span class="fa fa-calendar"></span></span>';
        $out .= '<input type="text" class="form-control margin-bottom" ';
        $out .= 'aria-describedby="tdate_addon" ';

        $out .= 'id="tdate" name="f[tdate]" ';
        $out .= 'value="';
        $out .= $tdate ?? '';
        $out .= '" ';
        $out .= 'data-provide="datepicker" ';
        $out .= 'data-date-format="';
        $out .= $date_format_service->datepicker_format($pp->schema());
        $out .= '" ';
        $out .= 'data-date-end-date="0d" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-immediate-updates="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'placeholder="';
        $out .= $date_format_service->datepicker_placeholder($pp->schema());
        $out .= '">';

        $out .= '</div>';
        $out .= '</div>';

        if ($service_stuff_enabled)
        {
            $out .= '<div class="col-sm-10">';
            $out .= '<div class="input-group margin-bottom custom-checkbox">';

            foreach (MessageTypeCnst::SERVICE_STUFF_TPL_ARY as $key => $d)
            {
                if ($key === 'null-service-stuff' && !$count_ary['null-service-stuff'])
                {
                    continue;
                }

                $label = '<span class="btn btn-';
                $label .= $d['btn_class'];
                $label .= '"';

                if (isset($d['title']))
                {
                    $label .= ' title="' . $d['title'] . '"';
                }

                $label .= '>';
                $label .= $d['label'];
                $label .= ' (';
                $label .= $count_ary[$key];
                $label .= ')</span>';

                $out .= strtr(BulkCnst::TPL_CHECKBOX_INLINE, [
                    '%name%'        => 'f[' . $key . ']',
                    '%attr%'        => isset($filter[$key]) ? ' checked' : '',
                    '%label%'       => $label,
                ]);
            }

            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="col-sm-2">';
        $out .= '<input type="submit" value="Toon" ';
        $out .= 'class="btn btn-default btn-block">';
        $out .= '</div>';

        $out .= '</div>';

        $params_form = array_merge($params, $pp->ary());
        unset($params_form['role_short']);
        unset($params_form['system']);
        unset($params_form['f']);
        unset($params_form['p']['start']);

        $params_form = http_build_query($params_form, 'prefix', '&');
        $params_form = urldecode($params_form);
        $params_form = explode('&', $params_form);

        foreach ($params_form as $param)
        {
            [$name, $value] = explode('=', $param);

            if (!isset($value) || $value === '')
            {
                continue;
            }

            $out .= '<input name="' . $name . '" ';
            $out .= 'value="' . $value . '" type="hidden">';
        }

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= $pagination_render->get();

        if (!count($transactions))
        {
            $out .= '<br>';
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-body">';
            $out .= '<p>Er zijn geen resultaten.</p>';
            $out .= '</div></div>';
            $out .= $pagination_render->get();

            $menu_service->set('transactions');

            return $this->render($template, [
                'content'   => $out,
                'filtered'  => $filtered,
                'is_self'   => $is_self,
                'uid'       => $filter['uid'] ?? 0,
                'balance'   => $balance ?? 0,
                'schema'    => $pp->schema(),
            ]);
        }

        $out .= '<div class="panel panel-primary printview">';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover footable csv transactions" ';
        $out .= 'data-sort="false">';
        $out .= '<thead>';
        $out .= '<tr>';

        foreach ($tableheader_ary as $key_orderby => $data)
        {
            $out .= '<th';

            if (isset($data['data_hide']))
            {
                $out .= ' data-hide="';
                $out .= $data['data_hide'];
                $out .= '"';
            }

            $out .= '>';

            if (isset($data['no_sort']))
            {
                $out .= $data['lbl'];
            }
            else
            {
                $h_params = $params;

                $h_params['s'] = [
                    'orderby' 	=> $key_orderby,
                    'asc'		=> $data['asc'],
                ];

                $out .= $link_render->link_fa('transactions', $pp->ary(),
                    $h_params, $data['lbl'], [], $data['fa']);
            }

            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';
        $out .= '<tbody>';

        if ($filter_uid)
        {
            foreach($transactions as $t)
            {
                $out .= '<tr';

                if ($config_service->get_intersystem_en($pp->schema()) && ($t['real_to'] || $t['real_from']))
                {
                    $out .= ' class="warning"';
                }

                $out .= '>';
                $out .= '<td>';

                $link_description = $link_render->link_no_attr('transactions_show', $pp->ary(),
                    ['id' => $t['id']], $t['description']);

                if ($bulk_actions_enabled && $pp->is_admin())
                {
                    $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                        '%id%'      => $t['id'],
                        '%attr%'    => isset($selected_transactions[$t['id']]) ? ' checked' : '',
                        '%label%'   => $link_description,
                    ]);
                }
                else
                {
                    $out .= $link_description;
                }

                $out .= '</td>';

                $out .= '<td>';
                $out .= '<span class="text-';

                if ($t['id_from'] === $filter['uid'])
                {
                    $out .= 'danger">-';
                }
                else
                {
                    $out .= 'success">+';
                }

                $out .= $t['amount'];
                $out .= '</span></td>';

                $out .= '<td>';
                $out .= $date_format_service->get($t['created_at'], 'min', $pp->schema());
                $out .= '</td>';

                $out .= '<td>';

                if ($t['id_from'] === $filter['uid'])
                {
                    if ($t['real_to'])
                    {
                        $out .= '<span class="btn btn-default">';
                        $out .= '<i class="fa fa-share-alt"></i></span> ';

                        if (isset($t['inter_transaction']))
                        {
                            if (isset($su_intersystem_ary[$t['inter_schema']]))
                            {
                                $out .= $account_render->inter_link($t['inter_transaction']['id_to'],
                                    $t['inter_schema'], $su);
                            }
                            else
                            {
                                $out .= $account_render->str($t['inter_transaction']['id_to'],
                                    $t['inter_schema']);
                            }
                        }
                        else
                        {
                            $out .= $t['real_to'];
                        }

                        $out .= '</dd>';
                    }
                    else
                    {
                        $out .= $account_render->link($t['id_to'], $pp->ary());
                    }
                }
                else
                {
                    if ($t['real_from'])
                    {
                        $out .= '<span class="btn btn-default">';
                        $out .= '<i class="fa fa-share-alt"></i></span> ';

                        if (isset($t['inter_transaction']))
                        {
                            if (isset($su_intersystem_ary[$t['inter_schema']]))
                            {
                                $out .= $account_render->inter_link($t['inter_transaction']['id_from'],
                                    $t['inter_schema'], $su);
                            }
                            else
                            {
                                $out .= $account_render->str($t['inter_transaction']['id_from'],
                                    $t['inter_schema']);
                            }
                        }
                        else
                        {
                            $out .= $t['real_from'];
                        }

                        $out .= '</dd>';
                    }
                    else
                    {
                        $out .= $account_render->link($t['id_from'], $pp->ary());
                    }
                }

                $out .= '</td>';
                $out .= '</tr>';
            }
        }
        else
        {
            foreach($transactions as $t)
            {
                $out .= '<tr';

                if ($config_service->get_intersystem_en($pp->schema()) && ($t['real_to'] || $t['real_from']))
                {
                    $out .= ' class="warning"';
                }

                $out .= '>';
                $out .= '<td>';

                $link_description = $link_render->link_no_attr('transactions_show', $pp->ary(),
                    ['id' => $t['id']], $t['description']);

                if ($bulk_actions_enabled && $pp->is_admin())
                {
                    $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                        '%id%'      => $t['id'],
                        '%attr%'    => isset($selected_transactions[$t['id']]) ? ' checked' : '',
                        '%label%'   => $link_description,
                    ]);
                }
                else
                {
                    $out .= $link_description;
                }

                $out .= '</td>';

                $out .= '<td>';
                $out .= $t['amount'];
                $out .= '</td>';

                $out .= '<td>';
                $out .= $date_format_service->get($t['created_at'], 'min', $pp->schema());
                $out .= '</td>';

                $out .= '<td>';

                if ($t['real_from'])
                {
                    $out .= '<span class="btn btn-default">';
                    $out .= '<i class="fa fa-share-alt"></i></span> ';

                    if (isset($t['inter_transaction']))
                    {
                        if (isset($su_intersystem_ary[$t['inter_schema']]))
                        {
                            $out .= $account_render->inter_link($t['inter_transaction']['id_from'],
                                $t['inter_schema'], $su);
                        }
                        else
                        {
                            $out .= $account_render->str($t['inter_transaction']['id_from'],
                                $t['inter_schema']);
                        }
                    }
                    else
                    {
                        $out .= $t['real_from'];
                    }

                    $out .= '</dd>';
                }
                else
                {
                    $out .= $account_render->link($t['id_from'], $pp->ary());
                }

                $out .= '</td>';

                $out .= '<td>';

                if ($t['real_to'])
                {
                    $out .= '<span class="btn btn-default">';
                    $out .= '<i class="fa fa-share-alt"></i></span> ';

                    if (isset($t['inter_transaction']))
                    {
                        if (isset($su_intersystem_ary[$t['inter_schema']]))
                        {
                            $out .= $account_render->inter_link($t['inter_transaction']['id_to'],
                                $t['inter_schema'], $su);
                        }
                        else
                        {
                            $out .= $account_render->str($t['inter_transaction']['id_to'],
                                $t['inter_schema']);
                        }
                    }
                    else
                    {
                        $out .= $t['real_to'];
                    }

                    $out .= '</dd>';
                }
                else
                {
                    $out .= $account_render->link($t['id_to'], $pp->ary());
                }

                $out .= '</td>';
                $out .= '</tr>';
            }
        }

        $out .= '</table></div></div>';

        $out .= $pagination_render->get();

        $out .= '<ul>';
        $out .= '<li>';
        $out .= 'Totaal: ';
        $out .= '<strong>';
        $out .= $amount_sum;
        $out .= '</strong> ';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</li>';
        $out .= self::get_valuation($config_service, $pp->schema());
        $out .= '</ul>';

        if ($pp->is_admin() && $bulk_actions_enabled)
        {
            $out .= BulkCnst::TPL_SELECT_BUTTONS;

            $out .= '<h3>Bulk acties met geselecteerde transacties</h3>';
            $out .= '<div class="panel panel-info">';
            $out .= '<div class="panel-heading">';

            $out .= '<ul class="nav nav-tabs" role="tablist">';

            if ($service_stuff_enabled)
            {
                $out .= '<li class="active"><a href="#service_stuff_tab" ';
                $out .= 'data-toggle="tab">Diensten / Spullen</a></li>';
            }

            $out .= '</ul>';

            $out .= '<div class="tab-content">';

            if ($service_stuff_enabled)
            {
                $out .= '<div role="tabpanel" class="tab-pane active" id="service_stuff_tab">';
                $out .= '<h3>Diensten of spullen</h3>';
                $out .= '<form method="post">';

                $out .= '<div class="form-group">';
                $out .= '<div class="custom-radio">';

                foreach (MessageTypeCnst::SERVICE_STUFF_TPL_ARY as $key => $render_data)
                {
                    $label = '<span class="btn btn-';
                    $label .= $render_data['btn_class'];
                    $label .= '"';

                    if (isset($render_data['title']))
                    {
                        $label .= ' title="' . $render_data['title'] . '"';
                    }

                    $label .= '>';
                    $label .= $render_data['label'];
                    $label .= '</span>';

                    $out .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                        '%name%'    => 'bulk_field[service_stuff]',
                        '%value%'   => $key,
                        '%attr%'    => ' required',
                        '%label%'   => $label,
                    ]);
                }

                $out .= '</div>';
                $out .= '</div>';

                $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[service_stuff]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste transacties geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $out .= '<input type="submit" value="Aanpassen" ';
                $out .= 'name="bulk_submit[service_stuff]" class="btn btn-primary btn-lg">';
                $out .= $form_token_service->get_hidden_input();
                $out .= '</form>';
                $out .= '</div>';
            }

            $out .= '<div class="clearfix"></div>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $menu_service->set('transactions');

        return $this->render($template, [
            'content'   => $out,
            'filtered'  => $filtered,
            'is_self'   => $is_self,
            'uid'       => $filter['uid'] ?? 0,
            'balance'   => $balance ?? 0,
            'schema'    => $pp->schema(),
        ]);
    }

    static public function get_valuation(
        ConfigService $config_service,
        string $schema
    ):string
    {
        $out = '';

        if ($config_service->get_bool('transactions.currency.timebased_en', $schema)
            && $config_service->get_int('transactions.currency.per_hour_ratio', $schema) > 0)
        {
            $out .= '<li id="info_ratio">Valuatie: <span class="num">';
            $out .= $config_service->get_int('transactions.currency.per_hour_ratio', $schema);
            $out .= '</span> ';
            $out .= $config_service->get('currency', $schema);
            $out .= ' per uur</li>';
        }

        return $out;
    }
}
