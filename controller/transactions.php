<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use service\config;

class transactions
{
    public function get(Request $request, app $app):Response
    {
        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);
        $inline_en = $request->query->get('inline', false) ? true : false;

        $intersystem_account_schemas = $app['intersystems']->get_eland_accounts_schemas($app['tschema']);

        $s_inter_schema_check = array_merge($app['intersystems']->get_eland($app['tschema']),
            [$app['s_schema'] => true]);

        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && isset($filter['uid'])
            && $app['s_id'] == $filter['uid'];

        $params_sql = $where_sql = $where_code_sql = [];

        $params = [
            's'	=> [
                'orderby'	=> $sort['orderby'] ?? 'cdate',
                'asc'		=> $sort['asc'] ?? 0,
            ],
            'p'	=> [
                'start'		=> $pag['start'] ?? 0,
                'limit'		=> $pag['limit'] ?? 25,
            ],
        ];

        if (isset($filter['uid']))
        {
            $filter['fcode'] = $app['account']->str($filter['uid'], $app['tschema']);
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

            $fuid = $app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.users
                where letscode = ?', [$fcode]);

            if ($fuid)
            {
                $fuid_sql = 't.id_from ';
                $fuid_sql .= $filter['andor'] === 'nor' ? '<>' : '=';
                $fuid_sql .= ' ?';
                $where_code_sql[] = $fuid_sql;
                $params_sql[] = $fuid;

                $fcode = $app['account']->str($fuid, $app['tschema']);
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

            $tuid = $app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.users
                where letscode = \'' . $tcode . '\'');

            if ($tuid)
            {
                $tuid_sql = 't.id_to ';
                $tuid_sql .= $filter['andor'] === 'nor' ? '<>' : '=';
                $tuid_sql .= ' ?';
                $where_code_sql[] = $tuid_sql;
                $params_sql[] = $tuid;

                $tcode = $app['account']->str($tuid, $app['tschema']);
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

        $where_sql = array_merge($where_sql, $where_code_sql);

        if (isset($filter['fdate']) && $filter['fdate'])
        {
            $fdate_sql = $app['date_format']->reverse($filter['fdate'], $app['tschema']);

            if ($fdate_sql === '')
            {
                $app['alert']->warning('De begindatum is fout geformateerd.');
            }
            else
            {
                $where_sql[] = 't.date >= ?';
                $params_sql[] = $fdate_sql;
                $params['f']['fdate'] = $fdate = $filter['fdate'];
            }
        }

        if (isset($filter['tdate']) && $filter['tdate'])
        {
            $tdate_sql = $app['date_format']->reverse($filter['tdate'], $app['tschema']);

            if ($tdate_sql === '')
            {
                $app['alert']->warning('De einddatum is fout geformateerd.');
            }
            else
            {
                $where_sql[] = 't.date <= ?';
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
            from ' . $app['tschema'] . '.transactions t ' .
            $where_sql . '
            order by t.' . $params['s']['orderby'] . ' ';
        $query .= $params['s']['asc'] ? 'asc ' : 'desc ';
        $query .= ' limit ' . $params['p']['limit'];
        $query .= ' offset ' . $params['p']['start'];

        $transactions = $app['db']->fetchAll($query, $params_sql);

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
                $inter_transaction = $app['db']->fetchAssoc('select t.*
                    from ' . $inter_schema . '.transactions t
                    where t.transid = ?', [$t['transid']]);

                if ($inter_transaction)
                {
                    $transactions[$key]['inter_schema'] = $inter_schema;
                    $transactions[$key]['inter_transaction'] = $inter_transaction;
                }
            }
        }

        $row = $app['db']->fetchAssoc('select count(t.*), sum(t.amount)
            from ' . $app['tschema'] . '.transactions t ' .
            $where_sql, $params_sql);

        $row_count = $row['count'];
        $amount_sum = $row['sum'];

        $app['pagination']->init('transactions', $app['pp_ary'],
            $row_count, $params, $inline_en);

        $asc_preset_ary = [
            'asc'	=> 0,
            'fa' 	=> 'sort',
        ];

        $tableheader_ary = [
            'description' => array_merge($asc_preset_ary, [
                'lbl' => 'Omschrijving']),
            'amount' => array_merge($asc_preset_ary, [
                'lbl' => $app['config']->get('currency', $app['tschema'])]),
            'cdate'	=> array_merge($asc_preset_ary, [
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

        if (!$inline_en && ($app['s_admin'] || $app['s_user']))
        {
            if (isset($filter['uid']))
            {
                $user = $app['user_cache']->get($filter['uid'], $app['tschema']);
                $user_str = $app['account']->str($user['id'], $app['tschema']);

                if ($user['status'] != 7)
                {
                    if ($s_owner)
                    {
                        $app['btn_top']->add('transactions_add', $app['pp_ary'],
                            ['add' => 1], 'Transactie toevoegen');
                    }
                    else
                    {
                        $app['btn_top']->add_trans('transactions_add', $app['pp_ary'],
                            ['tuid' => $user['id']],
                            'Transactie naar ' . $user_str);
                    }
                }
            }
            else
            {
                $app['btn_top']->add('transactions_add', $app['pp_ary'],
                    [], 'Transactie toevoegen');
            }
        }

        if ($app['s_admin'])
        {
            $app['btn_nav']->csv();
        }

        $filtered = !isset($filter['uid']) && (
            (isset($filter['q']) && $filter['q'] !== '')
            || (isset($filter['fcode']) && $filter['fcode'] !== '')
            || (isset($filter['tcode']) && $filter['tcode'] !== '')
            || (isset($filter['fdate']) && $filter['fdate'] !== '')
            || (isset($filter['tdate']) && $filter['tdate'] !== ''));

        if (isset($filter['uid']))
        {
            if ($s_owner && !$inline_en)
            {
                $app['heading']->add('Mijn transacties');
            }
            else
            {
                $app['heading']->add($app['link']->link_no_attr('transactions', $app['pp_ary'],
                    ['f' => ['uid' => $filter['uid']]], 'Transacties'));

                $app['heading']->add(' van ');
                $app['heading']->add($app['account']->link($filter['uid'], $app['pp_ary']));
            }
        }
        else
        {
            $app['heading']->add('Transacties');
            $app['heading']->add_filtered($filtered);
        }

        $app['heading']->fa('exchange');

        $out = '';

        if (!$inline_en)
        {
            $app['heading']->btn_filter();

            $app['assets']->add(['datepicker']);

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

            $app['typeahead']->ini($app['pp_ary'])
                ->add('accounts', ['status' => 'active']);

            if (!$app['s_guest'])
            {
                $app['typeahead']->add('accounts', ['status' => 'extern']);
            }

            if ($app['s_admin'])
            {
                $app['typeahead']->add('accounts', ['status' => 'inactive']);
                $app['typeahead']->add('accounts', ['status' => 'ip']);
                $app['typeahead']->add('accounts', ['status' => 'im']);
            }

            $out .= '<input type="text" class="form-control" ';
            $out .= 'aria-describedby="fcode_addon" ';

            $out .= 'data-typeahead="';

            $out .= $app['typeahead']->str([
                'filter'		=> 'accounts',
                'newuserdays'	=> $app['config']->get('newuserdays', $app['tschema']),
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
            $out .= $app['select']->get_options($andor_options, $filter['andor'] ?? 'and');
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
            $out .= $app['date_format']->datepicker_format($app['tschema']);
            $out .= '" ';
            $out .= 'data-date-default-view-date="-1y" ';
            $out .= 'data-date-end-date="0d" ';
            $out .= 'data-date-language="nl" ';
            $out .= 'data-date-today-highlight="true" ';
            $out .= 'data-date-autoclose="true" ';
            $out .= 'data-date-immediate-updates="true" ';
            $out .= 'data-date-orientation="bottom" ';
            $out .= 'placeholder="';
            $out .= $app['date_format']->datepicker_placeholder($app['tschema']);
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
            $out .= $app['date_format']->datepicker_format($app['tschema']);
            $out .= '" ';
            $out .= 'data-date-end-date="0d" ';
            $out .= 'data-date-language="nl" ';
            $out .= 'data-date-today-highlight="true" ';
            $out .= 'data-date-autoclose="true" ';
            $out .= 'data-date-immediate-updates="true" ';
            $out .= 'data-date-orientation="bottom" ';
            $out .= 'placeholder="';
            $out .= $app['date_format']->datepicker_placeholder($app['tschema']);
            $out .= '">';

            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="col-sm-2">';
            $out .= '<input type="submit" value="Toon" ';
            $out .= 'class="btn btn-default btn-block">';
            $out .= '</div>';

            $out .= '</div>';

            $params_form = $params;
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
        }
        else
        {
            $out .= '<div class="row">';
            $out .= '<div class="col-md-12">';

            $app['heading']->add_inline_btn($app['btn_top']->get());
            $out .= $app['heading']->get_h3();
        }

        $out .= $app['pagination']->get();

        if (!count($transactions))
        {
            $out .= '<br>';
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-body">';
            $out .= '<p>Er zijn geen resultaten.</p>';
            $out .= '</div></div>';
            $out .= $app['pagination']->get();

            $app['tpl']->add($out);
            $app['tpl']->menu('transactions');

            return $app['tpl']->get($request);
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

                $out .= $app['link']->link_fa('transactions', $app['pp_ary'],
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

                if ($app['intersystem_en'] && ($t['real_to'] || $t['real_from']))
                {
                    $out .= ' class="warning"';
                }

                $out .= '>';
                $out .= '<td>';

                $out .= $app['link']->link_no_attr('transactions_show', $app['pp_ary'],
                    ['id' => $t['id']], $t['description']);

                $out .= '</td>';

                $out .= '<td>';
                $out .= '<span class="text-';

                if ($t['id_from'] == $filter['uid'])
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
                $out .= $app['date_format']->get($t['cdate'], 'min', $app['tschema']);
                $out .= '</td>';

                $out .= '<td>';

                if ($t['id_from'] == $filter['uid'])
                {
                    if ($t['real_to'])
                    {
                        $out .= '<span class="btn btn-default">';
                        $out .= '<i class="fa fa-share-alt"></i></span> ';

                        if (isset($t['inter_transaction']))
                        {
                            if ($s_inter_schema_check[$t['inter_schema']])
                            {
                                $out .= $app['account']->inter_link($t['inter_transaction']['id_to'],
                                    $t['inter_schema']);
                            }
                            else
                            {
                                $out .= $app['account']->str($t['inter_transaction']['id_to'],
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
                        $out .= $app['account']->link($t['id_to'], $app['pp_ary']);
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
                            if ($s_inter_schema_check[$t['inter_schema']])
                            {
                                $out .= $app['account']->inter_link($t['inter_transaction']['id_from'],
                                    $t['inter_schema']);
                            }
                            else
                            {
                                $out .= $app['account']->str($t['inter_transaction']['id_from'],
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
                        $out .= $app['account']->link($t['id_from'], $app['pp_ary']);
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

                if ($app['intersystem_en'] && ($t['real_to'] || $t['real_from']))
                {
                    $out .= ' class="warning"';
                }

                $out .= '>';
                $out .= '<td>';
                $out .= $app['link']->link_no_attr('transactions_show', $app['pp_ary'],
                    ['id' => $t['id']], $t['description']);
                $out .= '</td>';

                $out .= '<td>';
                $out .= $t['amount'];
                $out .= '</td>';

                $out .= '<td>';
                $out .= $app['date_format']->get($t['cdate'], 'min', $app['tschema']);
                $out .= '</td>';

                $out .= '<td>';

                if ($t['real_from'])
                {
                    $out .= '<span class="btn btn-default">';
                    $out .= '<i class="fa fa-share-alt"></i></span> ';

                    if (isset($t['inter_transaction']))
                    {
                        if ($s_inter_schema_check[$t['inter_schema']])
                        {
                            $out .= $app['account']->inter_link($t['inter_transaction']['id_from'],
                                $t['inter_schema']);
                        }
                        else
                        {
                            $out .= $app['account']->str($t['inter_transaction']['id_from'],
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
                    $out .= $app['account']->link($t['id_from'], $app['pp_ary']);
                }

                $out .= '</td>';

                $out .= '<td>';

                if ($t['real_to'])
                {
                    $out .= '<span class="btn btn-default">';
                    $out .= '<i class="fa fa-share-alt"></i></span> ';

                    if (isset($t['inter_transaction']))
                    {
                        if ($s_inter_schema_check[$t['inter_schema']])
                        {
                            $out .= $app['account']->inter_link($t['inter_transaction']['id_to'],
                                $t['inter_schema']);
                        }
                        else
                        {
                            $out .= $app['account']->str($t['inter_transaction']['id_to'],
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
                    $out .= $app['account']->link($t['id_to'], $app['pp_ary']);
                }

                $out .= '</td>';
                $out .= '</tr>';
            }
        }

        $out .= '</table></div></div>';

        $out .= $app['pagination']->get();

        if ($inline_en)
        {
            $out .= '</div></div>';

            return new Response($out);
        }
        else
        {
            $out .= '<ul>';
            $out .= '<li>';
            $out .= 'Totaal: ';
            $out .= '<strong>';
            $out .= $amount_sum;
            $out .= '</strong> ';
            $out .= $app['config']->get('currency', $app['tschema']);
            $out .= '</li>';
            $out .= self::get_valuation($app['config'], $app['tschema']);
            $out .= '</ul>';
        }

        $app['tpl']->add($out);
        $app['tpl']->menu('transactions');

        return $app['tpl']->get($request);
    }


    static public function get_valuation(config $config, string $schema):string
    {
        $out = '';

        if ($config->get('template_lets', $schema)
            && $config->get('currencyratio', $schema) > 0)
        {
            $out .= '<li id="info_ratio">Valuatie: <span class="num">';
            $out .= $config->get('currencyratio', $schema);
            $out .= '</span> ';
            $out .= $config->get('currency', $schema);
            $out .= ' per uur</li>';
        }

        return $out;
    }
}