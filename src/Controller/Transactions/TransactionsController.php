<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Cnst\BulkCnst;
use App\Cnst\MessageTypeCnst;
use App\Command\Transactions\TransactionsFilterCommand;
use App\Form\Type\Transactions\TransactionsFilterType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
        AccountRender $account_render,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su
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

        $filter_command = new TransactionsFilterCommand();

        if ($request->query->has('uid'))
        {
            $uid = (int) $request->query->get('uid');
        }

        if ($is_self)
        {
            $uid = $su->id();
        }

        if (isset($uid))
        {
            $filter_command->from_account = $uid;
            $filter_command->to_account = $uid;
            $filter_command->account_logic = 'or';
        }

        $filter_form = $this->createForm(TransactionsFilterType::class, $filter_command);
        $filter_form->handleRequest($request);
        $filter_command = $filter_form->getData();

        $f_params = $request->query->all('f');
        $filter_form_error = (isset($f_params['from_account']) && !isset($filter_command->from_account))
            || (isset($f_params['to_account']) && !isset($filter_command->to_account));

        $pag = $request->query->all('p');
        $sort = $request->query->all('s');

        $pag_start = $pag['start'] ?? 0;
        $pag_limit = $pag['limit'] ?? 25;
        $sort_orderby = $sort['orderby'] ?? 'created_at';
        $sort_asc = isset($sort['asc']) && $sort['asc'] ? true : false;

        $all_params = $request->query->all();

        $selected_transactions = $request->request->all('sel');
        $bulk_field = $request->request->all('bulk_field');
        $bulk_verify = $request->request->all('bulk_verify');
        $bulk_submit = $request->request->all('bulk_submit');

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

                $res = $db->executeQuery('select *
                    from ' . $pp->schema() . '.transactions
                    where id in (?)',
                    [array_keys($selected_transactions)],
                    [Db::PARAM_INT_ARRAY]);

                while ($row = $res->fetchAssociative())
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

                $redirect_route = 'transactions' . ($is_self ? '_self' : '');

                return $this->redirectToRoute($redirect_route, $pp->ary());
            }

            $alert_service->error($errors);
        }

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

        if (isset($filter_command->q))
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][] = 't.description ilike ?';
            $sql['q']['params'][] = '%' . $filter_command->q . '%';
            $sql['q']['types'][] = \PDO::PARAM_STR;
        }

        $key_code_where_or = 'where';
        $key_code_where_or .= isset($filter_command->account_logic) && $filter_command->account_logic === 'or' ? '_or' : '';
        $sql['account'] = $sql_map;

        if (isset($filter_command->from_account))
        {
            $fuid_sql = 't.id_from ';
            $fuid_sql .= $filter_command->account_logic === 'nor' ? '<>' : '=';
            $fuid_sql .= ' ?';

            $sql['account'][$key_code_where_or][] = $fuid_sql;
            $sql['account']['params'][] = $filter_command->from_account;
            $sql['account']['types'][] = \PDO::PARAM_STR;
        }

        if (isset($filter_command->to_account))
        {
            $tuid_sql = 't.id_to ';
            $tuid_sql .= $filter_command->account_logic === 'nor' ? '<>' : '=';
            $tuid_sql .= ' ?';

            $sql['account'][$key_code_where_or][] = $tuid_sql;
            $sql['account']['params'][] = $filter_command->to_account;
            $sql['account']['types'][] = \PDO::PARAM_STR;
        }

        if (count($sql['account']['where_or']))
        {
            $sql['code']['where'] = [' ( ' . implode(' or ', $sql['account']['where_or']) . ' ) '];
        }

        if (isset($filter_command->from_date))
        {
            $sql['from_date'] = $sql_map;

            $from_date_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->from_date . ' UTC'));

            $sql['from_date']['where'][] = 't.created_at >= ?';
            $sql['from_date']['params'][] = $from_date_immutable;
            $sql['from_date']['types'][] = Types::DATETIME_IMMUTABLE;
        }


        if (isset($filter_command->to_date))
        {
            $sql['to_date'] = $sql_map;

            $to_date_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->to_date . ' UTC'));

            $sql['to_date']['where'][] = 't.created_at <= ?';
            $sql['to_date']['params'][] = $to_date_immutable;
            $sql['to_date']['types'][] = Types::DATETIME_IMMUTABLE;
        }

        $filter_service_stuff = $service_stuff_enabled
            && (isset($filter_command->srvc)
            && $filter_command->srvc
        );

        if ($filter_service_stuff)
        {
            $sql['service_stuff'] = $sql_map;

            if (in_array('srvc', $filter_command->srvc))
            {
                $sql['service_stuff']['where_or'][] = 't.service_stuff = \'service\'';
            }

            if (in_array('stff', $filter_command->srvc))
            {
                $sql['service_stuff']['where_or'][] = 't.service_stuff = \'stuff\'';
            }

            if (in_array('null', $filter_command->srvc))
            {
                $sql['service_stuff']['where_or'][] = 't.service_stuff is null';
            }

            if (count($sql['service_stuff']['where_or']))
            {
                $sql['service_stuff']['where'][] = '(' . implode(' or ', $sql['service_stuff']['where_or']) . ')';
            }
        }

        $sql['pagination'] = $sql_map;
        $sql['pagination']['params'][] = $pag_limit;
        $sql['pagination']['types'][] = \PDO::PARAM_INT;
        $sql['pagination']['params'][] = $pag_start;
        $sql['pagination']['types'][] = \PDO::PARAM_INT;

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));

        $query = 'select t.*
            from ' . $pp->schema() . '.transactions t
            where ' . $sql_where . '
            order by t.' . $sort_orderby . ' ';
        $query .= $sort_asc ? 'asc ' : 'desc ';
        $query .= 'limit ? offset ?';

        $res = $db->executeQuery($query,
            array_merge(...array_column($sql, 'params')),
            array_merge(...array_column($sql, 'types')));

        $transactions = [];
        $inter_fetch = [];

        while ($row = $res->fetchAssociative())
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
            'null_service_stuff'    => 0,
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

            $res = $db->executeQuery($count_service_stuff_query,
                array_merge(...array_column($sql_omit_service_stuff, 'params')),
                array_merge(...array_column($sql_omit_service_stuff, 'types')));

            while($row = $res->fetchAssociative())
            {
                $count_ary[$row['service_stuff'] ?? 'null_service_stuff'] = $row['count'];
            }
        }

        $asc_preset_ary = [
            'asc'	=> 0,
            'fa' 	=> 'sort',
        ];

        $tableheader_ary = [
            'description' => [
                ...$asc_preset_ary,
                'lbl' => 'Omschrijving',
            ],
            'amount' => [
                ...$asc_preset_ary,
                'lbl' => $config_service->get_str('transactions.currency.name', $pp->schema()),
            ],
            'created_at'	=> [
                ...$asc_preset_ary,
                'lbl' 		=> 'Tijdstip',
                'data_hide' => 'phone',
            ],
        ];

        if (isset($uid))
        {
            $tableheader_ary['user'] = [
                ...$asc_preset_ary,
                'lbl'			=> 'Tegenpartij',
                'data_hide'		=> 'phone, tablet',
                'no_sort'		=> true,
            ];
        }
        else
        {
            $tableheader_ary += [
                'from_user' => [
                    ...$asc_preset_ary,
                    'lbl' 		=> 'Van',
                    'data_hide'	=> 'phone, tablet',
                    'no_sort'	=> true,
                ],
                'to_user' => [
                    ...$asc_preset_ary,
                    'lbl' 		=> 'Aan',
                    'data_hide'	=> 'phone, tablet',
                    'no_sort'	=> true,
                ],
            ];
        }

        $tableheader_ary[$sort_orderby]['asc']
            = $sort_asc ? 0 : 1;
        $tableheader_ary[$sort_orderby]['fa']
            = $sort_asc ? 'sort-asc' : 'sort-desc';

        $filtered = !isset($uid) && (
            isset($filter_command->q)
            || isset($filter_command->from_account)
            || isset($filter_command->to_account)
            || isset($filter_command->from_date)
            || isset($filter_command->to_date)
            || $filter_service_stuff
        );

        $filter_collapse = !($filtered || $filter_form_error);

        $template = 'transactions/transactions_';
        $template .= isset($uid) ? 'uid' : 'list';
        $template .= '.html.twig';

        $out = '';

        $out .= '<div class="panel panel-primary printview">';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover footable csv transactions" ';
        $out .= 'data-sort="false">';
        $out .= '<thead>';
        $out .= '<tr>';

        $th_params = $all_params;
        unset($th_params['p']['start']);

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
                $th_params['s'] = [
                    'orderby' 	=> $key_orderby,
                    'asc'		=> $data['asc'],
                ];

                $out .= $link_render->link_fa('transactions', $pp->ary(),
                    $th_params, $data['lbl'], [], $data['fa']);
            }

            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';
        $out .= '<tbody>';

        if (isset($uid))
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

                if ($t['id_from'] === $uid)
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

                if ($t['id_from'] === $uid)
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

        if ($pp->is_admin() && $bulk_actions_enabled)
        {
            $blk = BulkCnst::TPL_SELECT_BUTTONS;

            $blk .= '<h3>Bulk acties met geselecteerde transacties</h3>';
            $blk .= '<div class="panel panel-info">';
            $blk .= '<div class="panel-heading">';

            $blk .= '<ul class="nav nav-tabs" role="tablist">';

            if ($service_stuff_enabled)
            {
                $blk .= '<li class="active"><a href="#service_stuff_tab" ';
                $blk .= 'data-toggle="tab">Diensten / Spullen</a></li>';
            }

            $blk .= '</ul>';

            $blk .= '<div class="tab-content">';

            if ($service_stuff_enabled)
            {
                $blk .= '<div role="tabpanel" class="tab-pane active" id="service_stuff_tab">';
                $blk .= '<h3>Diensten of spullen</h3>';
                $blk .= '<form method="post">';

                $blk .= '<div class="form-group">';

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

                    $blk .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                        '%name%'    => 'bulk_field[service_stuff]',
                        '%value%'   => $key,
                        '%attr%'    => ' required',
                        '%label%'   => $label,
                    ]);
                }

                $blk .= '</div>';

                $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[service_stuff]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste transacties geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $blk .= '<input type="submit" value="Aanpassen" ';
                $blk .= 'name="bulk_submit[service_stuff]" class="btn btn-primary btn-lg">';
                $blk .= $form_token_service->get_hidden_input();
                $blk .= '</form>';
                $blk .= '</div>';
            }

            $blk .= '<div class="clearfix"></div>';
            $blk .= '</div>';
            $blk .= '</div>';
            $blk .= '</div>';
        }

        return $this->render($template, [
            'data_list_raw'         => $out,
            'filter_form'           => $filter_form->createView(),
            'filtered'              => $filtered,
            'filter_collapse'       => $filter_collapse,
            'count_ary'             => $count_ary,
            'bulk_actions_raw'      => $blk ?? '',
            'row_count'             => $row_count,
            'amount_sum'            => $amount_sum,
            'is_self'               => $is_self,
            'bulk_actions_enabled'  => $bulk_actions_enabled,
            'uid'                   => $uid ?? null,
        ]);
    }
}
