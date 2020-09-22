<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
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
use App\Service\IntersystemsService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionsController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AccountRepository $account_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        HeadingRender $heading_render,
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

        $intersystem_account_schemas = $intersystems_service->get_eland_accounts_schemas($pp->schema());

        $su_intersystem_ary = $intersystems_service->get_eland($su->schema());
        $su_intersystem_ary[$su->schema()] = true;

        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        if (isset($filter['uid']))
        {
            $filter['uid'] = (int) $filter['uid'];

            $balance = $account_repository->get_balance($filter['uid'], $pp->schema());
        }

        $is_owner = isset($filter['uid'])
            && $su->is_owner($filter['uid']);

        $params_sql = $where_sql = $where_code_sql = [];

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

        if (isset($filter['uid']))
        {
            $filter['fcode'] = $account_render->str($filter['uid'], $pp->schema());
            $filter['tcode'] = $filter['fcode'];
            $filter['andor'] = 'or';
            $params['f']['uid'] = $filter['uid'];
        }

        if (isset($filter['q']) && $filter['q'])
        {
            $where_sql[] = 't.description ilike ?';
            $params_sql[] = '%' . $filter['q'] . '%';
            $params['f']['q'] = $filter['q'];
        }

        if (isset($filter['fcode']) && $filter['fcode'])
        {
            [$fcode] = explode(' ', trim($filter['fcode']));
            $fcode = trim($fcode);

            $fuid = $db->fetchColumn('select id
                from ' . $pp->schema() . '.users
                where code = ?', [$fcode]);

            if ($fuid)
            {
                $fuid_sql = 't.id_from ';
                $fuid_sql .= $filter['andor'] === 'nor' ? '<>' : '=';
                $fuid_sql .= ' ?';
                $where_code_sql[] = $fuid_sql;
                $params_sql[] = $fuid;

                $fcode = $account_render->str($fuid, $pp->schema());
            }
            else if ($filter['andor'] !== 'nor')
            {
                $where_code_sql[] = '1 = 2';
            }

            $params['f']['fcode'] = $fcode;
        }

        if (isset($filter['tcode']) && $filter['tcode'])
        {
            [$tcode] = explode(' ', trim($filter['tcode']));

            $tuid = $db->fetchColumn('select id
                from ' . $pp->schema() . '.users
                where code = \'' . $tcode . '\'');

            if ($tuid)
            {
                $tuid_sql = 't.id_to ';
                $tuid_sql .= $filter['andor'] === 'nor' ? '<>' : '=';
                $tuid_sql .= ' ?';
                $where_code_sql[] = $tuid_sql;
                $params_sql[] = $tuid;

                $tcode = $account_render->str($tuid, $pp->schema());
            }
            else if ($filter['andor'] !== 'nor')
            {
                $where_code_sql[] = '1 = 2';
            }

            $params['f']['tcode'] = $tcode;
        }

        if (count($where_code_sql) > 1 && $filter['andor'] === 'or')
        {
            $where_code_sql = [' ( ' . implode(' or ', $where_code_sql) . ' ) '];
        }

        $where_sql = [...$where_sql, ...$where_code_sql];

        if (isset($filter['fdate']) && $filter['fdate'])
        {
            $fdate_sql = $date_format_service->reverse($filter['fdate'], $pp->schema());

            if ($fdate_sql === '')
            {
                $alert_service->warning('De begindatum is fout geformateerd.');
            }
            else
            {
                $where_sql[] = 't.created_at >= ?';
                $params_sql[] = $fdate_sql;
                $params['f']['fdate'] = $fdate = $filter['fdate'];
            }
        }

        if (isset($filter['tdate']) && $filter['tdate'])
        {
            $tdate_sql = $date_format_service->reverse($filter['tdate'], $pp->schema());

            if ($tdate_sql === '')
            {
                $alert_service->warning('De einddatum is fout geformateerd.');
            }
            else
            {
                $where_sql[] = 't.created_at <= ?';
                $params_sql[] = $tdate_sql;
                $params['f']['tdate'] = $tdate = $filter['tdate'];
            }
        }

        if (count($where_sql))
        {
            $where_sql = ' where ' . implode(' and ', $where_sql) . ' ';
            $params['f']['andor'] = $filter['andor'];
        }
        else
        {
            $where_sql = '';
        }

        $query = 'select t.*
            from ' . $pp->schema() . '.transactions t ' .
            $where_sql . '
            order by t.' . $params['s']['orderby'] . ' ';
        $query .= $params['s']['asc'] ? 'asc ' : 'desc ';
        $query .= ' limit ' . $params['p']['limit'];
        $query .= ' offset ' . $params['p']['start'];

        $transactions = $db->fetchAll($query, $params_sql);

        foreach ($transactions as $key => $t)
        {
            if (!($t['real_from'] || $t['real_to']))
            {
                continue;
            }

            $inter_schema = false;

            if (isset($intersystem_account_schemas[$t['id_from']]))
            {
                $inter_schema = $intersystem_account_schemas[$t['id_from']];
            }
            else if (isset($intersystem_account_schemas[$t['id_to']]))
            {
                $inter_schema = $intersystem_account_schemas[$t['id_to']];
            }

            if ($inter_schema)
            {
                $inter_transaction = $db->fetchAssoc('select t.*
                    from ' . $inter_schema . '.transactions t
                    where t.transid = ?', [$t['transid']]);

                if ($inter_transaction)
                {
                    $transactions[$key]['inter_schema'] = $inter_schema;
                    $transactions[$key]['inter_transaction'] = $inter_transaction;
                }
            }
        }

        $row = $db->fetchAssoc('select count(t.*), sum(t.amount)
            from ' . $pp->schema() . '.transactions t ' .
            $where_sql, $params_sql);

        $row_count = $row['count'];
        $amount_sum = $row['sum'];

        $pagination_render->init('transactions', $pp->ary(),
            $row_count, $params);

        $asc_preset_ary = [
            'asc'	=> 0,
            'fa' 	=> 'sort',
        ];

        $tableheader_ary = [
            'description' => array_merge($asc_preset_ary, [
                'lbl' => 'Omschrijving']),
            'amount' => array_merge($asc_preset_ary, [
                'lbl' => $config_service->get('currency', $pp->schema())]),
            'created_at'	=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'Tijdstip',
                'data_hide' => 'phone'])
        ];

        if (isset($filter['uid']))
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

        if (isset($filter['uid']))
        {
            $user = $user_cache_service->get($filter['uid'], $pp->schema());
            $user_str = $account_render->str($user['id'], $pp->schema());
        }

        if ($pp->is_admin() || $pp->is_user())
        {
            if (isset($filter['uid']))
            {
                if ($user['status'] != 7)
                {
                    if ($is_owner)
                    {
                        $btn_top_render->add('transactions_add', $pp->ary(),
                            ['add' => 1], 'Transactie toevoegen');
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
            $btn_nav_render->csv();
        }

        $filtered = !isset($filter['uid']) && (
            (isset($filter['q']) && $filter['q'] !== '')
            || (isset($filter['fcode']) && $filter['fcode'] !== '')
            || (isset($filter['tcode']) && $filter['tcode'] !== '')
            || (isset($filter['fdate']) && $filter['fdate'] !== '')
            || (isset($filter['tdate']) && $filter['tdate'] !== ''));

        if (isset($filter['uid']))
        {
            if ($is_owner)
            {
                $heading_render->add('Mijn transacties');
            }
            else
            {
                $heading_render->add('Transacties van ');
                $heading_render->add_raw($account_render->link($filter['uid'], $pp->ary()));
            }

            $heading_render->add_sub_raw('Huidig saldo: <span class="label label-info">');
            $heading_render->add_sub((string) $balance);
            $heading_render->add_sub_raw('</span>&nbsp;');
            $heading_render->add_sub($config_service->get('currency', $pp->schema()));
        }
        else
        {
            $heading_render->add('Transacties');
            $heading_render->add_filtered($filtered);
        }

        $heading_render->fa('exchange');

        $out = '';

        $heading_render->btn_filter();

        $assets_service->add(['datepicker']);

        $out .= '<div class="panel panel-info';
        $out .= $filtered ? '' : ' collapse';
        $out .= '" id="filter">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get" class="form-horizontal">';

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

        $out .= '<div class="col-sm-5">';
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

        $out .= '<div class="col-sm-5">';
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

        $out .= '<div class="col-sm-2">';
        $out .= '<input type="submit" value="Toon" ';
        $out .= 'class="btn btn-default btn-block">';
        $out .= '</div>';

        $out .= '</div>';

        $params_form = array_merge($params, $pp->ary());
        unset($params_form['role_short']);
        unset($params_form['system']);
        unset($params_form['f']);
        unset($params_form['uid']);
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

            return $this->render('base/navbar.html.twig', [
                'content'   => $out,
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

        if (isset($filter['uid']))
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

                $out .= $link_render->link_no_attr('transactions_show', $pp->ary(),
                    ['id' => $t['id']], $t['description']);

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
                $out .= $link_render->link_no_attr('transactions_show', $pp->ary(),
                    ['id' => $t['id']], $t['description']);
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

        $menu_service->set('transactions');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    static public function get_valuation(
        ConfigService $config_service,
        string $schema
    ):string
    {
        $out = '';

        if ($config_service->get('template_lets', $schema)
            && $config_service->get('currencyratio', $schema) > 0)
        {
            $out .= '<li id="info_ratio">Valuatie: <span class="num">';
            $out .= $config_service->get('currencyratio', $schema);
            $out .= '</span> ';
            $out .= $config_service->get('currency', $schema);
            $out .= ' per uur</li>';
        }

        return $out;
    }
}
