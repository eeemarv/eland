<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use render\btn_nav;
use render\heading;
use cnst\status as cnst_status;
use cnst\role as cnst_role;

class users_list
{
    public function admin(Request $request, app $app, string $status):Response
    {
        return $this->get($request, $app, $status);
    }

    public function get(Request $request, app $app, string $status):Response
    {
        $st = self::get_st($app['s_admin'], $app['new_user_treshold']);

        $users_route = $app['s_admin'] ? 'users_list_admin' : 'users_list';

        $v_list = true;
        $v_tiles = false;
        $v_map = false;

        $sql_bind = [];

        if (isset($st[$status]['sql_bind']))
        {
            $sql_bind[] = $st[$status]['sql_bind'];
        }

        $params = ['status'	=> $status];

        $ref_geo = [];

        if ($v_list)
        {
            $type_contact = $app['db']->fetchAll('select id, abbrev, name
                from ' . $app['tschema'] . '.type_contact');

            $columns = [
                'u'		=> [
                    'letscode'		=> 'Code',
                    'name'			=> 'Naam',
                    'fullname'		=> 'Volledige naam',
                    'postcode'		=> 'Postcode',
                    'accountrole'	=> 'Rol',
                    'saldo'			=> 'Saldo',
                    'saldo_date'	=> 'Saldo op ',
                    'minlimit'		=> 'Min',
                    'maxlimit'		=> 'Max',
                    'comments'		=> 'Commentaar',
                    'hobbies'		=> 'Hobbies/interesses',
                ],
            ];

            if ($app['s_admin'])
            {
                $columns['u'] += [
                    'admincomment'	=> 'Admin commentaar',
                    'cron_saldo'	=> 'Periodieke Overzichts E-mail',
                    'cdate'			=> 'Gecreëerd',
                    'mdate'			=> 'Aangepast',
                    'adate'			=> 'Geactiveerd',
                    'lastlogin'		=> 'Laatst ingelogd',
                ];
            }

            foreach ($type_contact as $tc)
            {
                $columns['c'][$tc['abbrev']] = $tc['name'];
            }

            if (!$app['s_elas_guest'])
            {
                $columns['d'] = [
                    'distance'	=> 'Afstand',
                ];
            }

            $columns['m'] = [
                'wants'		=> 'Vraag',
                'offers'	=> 'Aanbod',
                'total'		=> 'Vraag en aanbod',
            ];

            $message_type_filter = [
                'wants'		=> ['want' => 'on'],
                'offers'	=> ['offer' => 'on'],
                'total'		=> '',
            ];

            $columns['a'] = [
                'trans'		=> [
                    'in'	=> 'Transacties in',
                    'out'	=> 'Transacties uit',
                    'total'	=> 'Transacties totaal',
                ],
                'amount'	=> [
                    'in'	=> $app['config']->get('currency', $app['tschema']) . ' in',
                    'out'	=> $app['config']->get('currency', $app['tschema']) . ' uit',
                    'total'	=> $app['config']->get('currency', $app['tschema']) . ' totaal',
                ],
            ];

            $columns['p'] = [
                'c'	=> [
                    'adr_split'	=> '.',
                ],
                'a'	=> [
                    'days'	=> '.',
                    'code'	=> '.',
                ],
                'u'	=> [
                    'saldo_date'	=> '.',
                ],
            ];

            $session_users_columns_key = 'users_columns_';
            $session_users_columns_key .= $app['pp_role'];
            $session_users_columns_key .= $app['s_elas_guest'] ? '_elas' : '';

            if (isset($_GET['sh']))
            {
                $show_columns = $_GET['sh'] ?? [];

                $show_columns = array_intersect_key_recursive($show_columns, $columns);

                $app['session']->set($session_users_columns_key, $show_columns);
            }
            else
            {
                if ($app['s_admin'] || $app['s_guest'])
                {
                    $preset_columns = [
                        'u'	=> [
                            'letscode'	=> 1,
                            'name'		=> 1,
                            'postcode'	=> 1,
                            'saldo'		=> 1,
                        ],
                    ];
                }
                else
                {
                    $preset_columns = [
                        'u' => [
                            'letscode'	=> 1,
                            'name'		=> 1,
                            'postcode'	=> 1,
                            'saldo'		=> 1,
                        ],
                        'c'	=> [
                            'gsm'	=> 1,
                            'tel'	=> 1,
                            'adr'	=> 1,
                        ],
                        'd'	=> [
                            'distance'	=> 1,
                        ],
                    ];
                }

                if ($app['s_elas_guest'])
                {
                    unset($columns['d']['distance']);
                }

                $show_columns = $app['session']->get($session_users_columns_key) ?? $preset_columns;
            }

            $adr_split = $show_columns['p']['c']['adr_split'] ?? '';
            $activity_days = $show_columns['p']['a']['days'] ?? 365;
            $activity_days = $activity_days < 1 ? 365 : $activity_days;
            $activity_filter_code = $show_columns['p']['a']['code'] ?? '';
            $saldo_date = $show_columns['p']['u']['saldo_date'] ?? '';
            $saldo_date = trim($saldo_date);

            $users = $app['db']->fetchAll('select u.*
                from ' . $app['tschema'] . '.users u
                where ' . $st[$status]['sql'] . '
                order by u.letscode asc', $sql_bind);

        // hack eLAS compatibility (in eLAND limits can be null)

            if (isset($show_columns['u']['minlimit']) || isset($show_columns['u']['maxlimit']))
            {
                foreach ($users as &$user)
                {
                    $user['minlimit'] = $user['minlimit'] === -999999999 ? '' : $user['minlimit'];
                    $user['maxlimit'] = $user['maxlimit'] === 999999999 ? '' : $user['maxlimit'];
                }
            }

            if (isset($show_columns['u']['fullname']))
            {
                foreach ($users as &$user)
                {
                    $user['fullname_access'] = $app['xdb']->get(
                        'user_fullname_access',
                        $user['id'],
                        $app['tschema']
                    )['data']['fullname_access'] ?? 'admin';

                    error_log($user['fullname_access']);
                }
            }

            if (isset($show_columns['u']['saldo_date']))
            {
                if ($saldo_date)
                {
                    $saldo_date_rev = $app['date_format']->reverse($saldo_date, 'min', $app['tschema']);
                }

                if ($saldo_date_rev === '' || $saldo_date == '')
                {
                    $saldo_date = $app['date_format']->get('', 'day', $app['tschema']);

                    array_walk($users, function(&$user, $user_id){
                        $user['saldo_date'] = $user['saldo'];
                    });
                }
                else
                {
                    $trans_in = $trans_out = [];
                    $datetime = new \DateTime($saldo_date_rev);

                    $rs = $app['db']->prepare('select id_to, sum(amount)
                        from ' . $app['tschema'] . '.transactions
                        where date <= ?
                        group by id_to');

                    $rs->bindValue(1, $datetime, 'datetime');

                    $rs->execute();

                    while($row = $rs->fetch())
                    {
                        $trans_in[$row['id_to']] = $row['sum'];
                    }

                    $rs = $app['db']->prepare('select id_from, sum(amount)
                        from ' . $app['tschema'] . '.transactions
                        where date <= ?
                        group by id_from');
                    $rs->bindValue(1, $datetime, 'datetime');

                    $rs->execute();

                    while($row = $rs->fetch())
                    {
                        $trans_out[$row['id_from']] = $row['sum'];
                    }

                    array_walk($users, function(&$user) use ($trans_out, $trans_in){
                        $user['saldo_date'] = 0;
                        $user['saldo_date'] += $trans_in[$user['id']] ?? 0;
                        $user['saldo_date'] -= $trans_out[$user['id']] ?? 0;
                    });
                }
            }

            if (isset($show_columns['c']) || (isset($show_columns['d']) && !$app['s_master']))
            {
                $c_ary = $app['db']->fetchAll('select tc.abbrev,
                        c.id_user, c.value, c.flag_public
                    from ' . $app['tschema'] . '.contact c, ' .
                        $app['tschema'] . '.type_contact tc, ' .
                        $app['tschema'] . '.users u
                    where tc.id = c.id_type_contact ' .
                        (isset($show_columns['c']) ? '' : 'and tc.abbrev = \'adr\' ') .
                        'and c.id_user = u.id
                        and ' . $st[$status]['sql'], $sql_bind);

                $contacts = [];

                foreach ($c_ary as $c)
                {
                    $contacts[$c['id_user']][$c['abbrev']][] = [$c['value'], $c['flag_public']];
                }
            }

            if (isset($show_columns['d']) && !$app['s_master'])
            {
                if (($app['s_guest'] && $app['s_schema'] && !$app['s_elas_guest'])
                    || !isset($contacts[$app['s_id']]['adr']))
                {
                    $my_adr = $app['db']->fetchColumn('select c.value
                        from ' . $app['s_schema'] . '.contact c, ' .
                            $app['s_schema'] . '.type_contact tc
                        where c.id_user = ?
                            and c.id_type_contact = tc.id
                            and tc.abbrev = \'adr\'', [$app['s_id']]);
                }
                else if (!$app['s_guest'])
                {
                    $my_adr = trim($contacts[$app['s_id']]['adr'][0][0]);
                }

                if (isset($my_adr))
                {
                    $ref_geo = $app['cache']->get('geo_' . $my_adr);
                }
            }

            if (isset($show_columns['m']))
            {
                $msgs_count = [];

                if (isset($show_columns['m']['offers']))
                {
                    $ary = $app['db']->fetchAll('select count(m.id), m.id_user
                        from ' . $app['tschema'] . '.messages m, ' .
                            $app['tschema'] . '.users u
                        where msg_type = 1
                            and m.id_user = u.id
                            and ' . $st[$status]['sql'] . '
                        group by m.id_user', $sql_bind);

                    foreach ($ary as $a)
                    {
                        $msgs_count[$a['id_user']]['offers'] = $a['count'];
                    }
                }

                if (isset($show_columns['m']['wants']))
                {
                    $ary = $app['db']->fetchAll('select count(m.id), m.id_user
                        from ' . $app['tschema'] . '.messages m, ' .
                            $app['tschema'] . '.users u
                        where msg_type = 0
                            and m.id_user = u.id
                            and ' . $st[$status]['sql'] . '
                        group by m.id_user', $sql_bind);

                    foreach ($ary as $a)
                    {
                        $msgs_count[$a['id_user']]['wants'] = $a['count'];
                    }
                }

                if (isset($show_columns['m']['total']))
                {
                    $ary = $app['db']->fetchAll('select count(m.id), m.id_user
                        from ' . $app['tschema'] . '.messages m, ' .
                            $app['tschema'] . '.users u
                        where m.id_user = u.id
                            and ' . $st[$status]['sql'] . '
                        group by m.id_user', $sql_bind);

                    foreach ($ary as $a)
                    {
                        $msgs_count[$a['id_user']]['total'] = $a['count'];
                    }
                }
            }

            if (isset($show_columns['a']))
            {
                $activity = [];

                $ts = gmdate('Y-m-d H:i:s', time() - ($activity_days * 86400));
                $sql_bind = [$ts];

                $activity_filter_code = trim($activity_filter_code);

                if ($activity_filter_code)
                {
                    [$code_only_activity_filter_code] = explode(' ', $activity_filter_code);
                    $and = ' and u.letscode <> ? ';
                    $sql_bind[] = trim($code_only_activity_filter_code);
                }
                else
                {
                    $and = ' and 1 = 1 ';
                }

                $trans_in_ary = $app['db']->fetchAll('select sum(t.amount),
                        count(t.id), t.id_to
                    from ' . $app['tschema'] . '.transactions t, ' .
                        $app['tschema'] . '.users u
                    where t.id_from = u.id
                        and t.cdate > ?' . $and . '
                    group by t.id_to', $sql_bind);

                $trans_out_ary = $app['db']->fetchAll('select sum(t.amount),
                        count(t.id), t.id_from
                    from ' . $app['tschema'] . '.transactions t, ' .
                        $app['tschema'] . '.users u
                    where t.id_to = u.id
                        and t.cdate > ?' . $and . '
                    group by t.id_from', $sql_bind);

                foreach ($trans_in_ary as $trans_in)
                {
                    if (!isset($activity[$trans_in['id_to']]))
                    {
                        $activity[$trans_in['id_to']] = [
                            'trans'	=> ['total' => 0],
                            'amount' => ['total' => 0],
                        ];
                    }

                    $activity[$trans_in['id_to']]['trans']['in'] = $trans_in['count'];
                    $activity[$trans_in['id_to']]['amount']['in'] = $trans_in['sum'];
                    $activity[$trans_in['id_to']]['trans']['total'] += $trans_in['count'];
                    $activity[$trans_in['id_to']]['amount']['total'] += $trans_in['sum'];
                }

                foreach ($trans_out_ary as $trans_out)
                {
                    if (!isset($activity[$trans_out['id_from']]))
                    {
                        $activity[$trans_out['id_from']] = [
                            'trans'	=> ['total' => 0],
                            'amount' => ['total' => 0],
                        ];
                    }

                    $activity[$trans_out['id_from']]['trans']['out'] = $trans_out['count'];
                    $activity[$trans_out['id_from']]['amount']['out'] = $trans_out['sum'];
                    $activity[$trans_out['id_from']]['trans']['total'] += $trans_out['count'];
                    $activity[$trans_out['id_from']]['amount']['total'] += $trans_out['sum'];
                }
            }
        }
        else
        {
            $users = $app['db']->fetchAll('select u.*
                from ' . $app['tschema'] . '.users u
                where ' . $st[$status]['sql'] . '
                order by u.letscode asc', $sql_bind);

            if ($v_map)
            {
                $c_ary = $app['db']->fetchAll('select tc.abbrev,
                    c.id_user, c.value, c.flag_public, c.id
                    from ' . $app['tschema'] . '.contact c, ' .
                        $app['tschema'] . '.type_contact tc
                    where tc.id = c.id_type_contact
                        and tc.abbrev in (\'mail\', \'tel\', \'gsm\', \'adr\')');

                $contacts = [];

                foreach ($c_ary as $c)
                {
                    $contacts[$c['id_user']][$c['abbrev']][] = [
                        $c['value'],
                        $c['flag_public'],
                        $c['id'],
                    ];
                }

                if (!$app['s_master'])
                {
                    if ($app['s_guest'] && $app['s_schema'] && !$app['s_elas_guest'])
                    {
                        $my_adr = $app['db']->fetchColumn('select c.value
                            from ' . $app['s_schema'] . '.contact c, ' . $app['s_schema'] . '.type_contact tc
                            where c.id_user = ?
                                and c.id_type_contact = tc.id
                                and tc.abbrev = \'adr\'', [$app['s_id']]);
                    }
                    else if (!$app['s_guest'])
                    {
                        $my_adr = trim($contacts[$app['s_id']]['adr'][0][0]);
                    }

                    if (isset($my_adr))
                    {
                        $ref_geo = $app['cache']->get('geo_' . $my_adr);
                    }
                }
            }
        }

        if ($app['s_admin'])
        {
            $app['btn_nav']->csv();

            $app['btn_top']->add('users_add', $app['pp_ary'],
                [], 'Gebruiker toevoegen');

            $app['btn_top']->local('#actions', 'Bulk acties', 'envelope-o');
        }

        $app['btn_nav']->columns_show();

        self::btn_nav($app['btn_nav'], $app['pp_ary'], $params, 'users_list');
        self::heading($app['heading']);

        $app['assets']->add([
            'calc_sum.js',
            'users_distance.js',
            'datepicker',
        ]);

        if ($app['s_admin'])
        {
            $app['assets']->add([
                'summernote',
                'table_sel.js',
                'rich_edit.js',
            ]);
        }

        $out = '';

        if ($v_list || $v_tiles)
        {
            $out .= '<form method="get">';

            foreach ($params as $k => $v)
            {
                $out .= '<input type="hidden" name="' . $k . '" value="' . $v . '">';
            }
        }

        if ($v_list)
        {
            $out .= '<div class="panel panel-info collapse" ';
            $out .= 'id="columns_show">';
            $out .= '<div class="panel-heading">';
            $out .= '<h2>Weergave kolommen</h2>';

            $out .= '<div class="row">';

            foreach ($columns as $group => $ary)
            {
                if ($group === 'p')
                {
                    continue;
                }

                if ($group === 'm' || $group === 'c')
                {
                    $out .= '</div>';
                }

                if ($group === 'u' || $group === 'c' || $group === 'm')
                {
                    $out .= '<div class="col-md-4">';
                }

                if ($group === 'c')
                {
                    $out .= '<h3>Contacten</h3>';
                }
                else if ($group === 'd')
                {
                    $out .= '<h3>Afstand</h3>';
                    $out .= '<p>Tussen eigen adres en adres van gebruiiker. ';
                    $out .= 'De kolom wordt niet getoond wanneer het eigen adres ';
                    $out .= 'niet ingesteld is.</p>';
                }
                else if ($group === 'a')
                {
                    $out .= '<h3>Transacties/activiteit</h3>';

                    $out .= '<div class="form-group">';
                    $out .= '<label for="p_activity_days" ';
                    $out .= 'class="control-label">';
                    $out .= 'In periode';
                    $out .= '</label>';
                    $out .= '<div class="input-group">';
                    $out .= '<span class="input-group-addon">';
                    $out .= 'dagen';
                    $out .= '</span>';
                    $out .= '<input type="number" ';
                    $out .= 'id="p_activity_days" ';
                    $out .= 'name="sh[p][a][days]" ';
                    $out .= 'value="';
                    $out .= $activity_days;
                    $out .= '" ';
                    $out .= 'size="4" min="1" class="form-control">';
                    $out .= '</div>';
                    $out .= '</div>';

                    $app['typeahead']->ini($app['pp_ary'])
                        ->add('accounts', ['status' => 'active']);

                    if (!$app['s_guest'])
                    {
                        $app['typeahead']->add('accounts', ['status' => 'extern']);
                    }

                    if ($app['s_admin'])
                    {
                        $app['typeahead']->add('accounts', ['status' => 'inactive'])
                            ->add('accounts', ['status' => 'ip'])
                            ->add('accounts', ['status' => 'im']);
                    }

                    $out .= '<div class="form-group">';
                    $out .= '<label for="p_activity_filter_letscode" ';
                    $out .= 'class="control-label">';
                    $out .= 'Exclusief tegenpartij';
                    $out .= '</label>';
                    $out .= '<div class="input-group">';
                    $out .= '<span class="input-group-addon">';
                    $out .= '<i class="fa fa-user"></i>';
                    $out .= '</span>';
                    $out .= '<input type="text" ';
                    $out .= 'name="sh[p][a][code]" ';
                    $out .= 'id="p_activity_filter_code" ';
                    $out .= 'value="';
                    $out .= $activity_filter_code;
                    $out .= '" ';
                    $out .= 'placeholder="Account Code" ';
                    $out .= 'class="form-control" ';
                    $out .= 'data-typeahead="';

                    $out .= $app['typeahead']->str([
                        'filter'		=> 'accounts',
                        'newuserdays'	=> $app['config']->get('newuserdays', $app['tschema']),
                    ]);

                    $out .= '">';
                    $out .= '</div>';
                    $out .= '</div>';

                    foreach ($ary as $a_type => $a_ary)
                    {
                        foreach($a_ary as $key => $lbl)
                        {
                            $checkbox_id = 'id_' . $group . '_' . $a_type . '_' . $key;

                            $out .= '<div class="checkbox">';
                            $out .= '<label for="';
                            $out .= $checkbox_id;
                            $out .= '">';
                            $out .= '<input type="checkbox" ';
                            $out .= 'id="';
                            $out .= $checkbox_id;
                            $out .= '" ';
                            $out .= 'name="sh[' . $group . '][' . $a_type . '][' . $key . ']" ';
                            $out .= 'value="1"';
                            $out .= isset($show_columns[$group][$a_type][$key]) ? ' checked="checked"' : '';
                            $out .= '> ' . $lbl;
                            $out .= '</label>';
                            $out .= '</div>';
                        }
                    }

                    $out .= '</div>';

                    continue;
                }
                else if ($group === 'm')
                {
                    $out .= '<h3>Vraag en aanbod</h3>';
                }

                foreach ($ary as $key => $lbl)
                {
                    $checkbox_id = 'id_' . $group . '_' . $key;

                    $out .= '<div class="checkbox">';
                    $out .= '<label for="';
                    $out .= $checkbox_id;
                    $out .= '">';
                    $out .= '<input type="checkbox" name="sh[';
                    $out .= $group . '][' . $key . ']" ';
                    $out .= 'id="';
                    $out .= $checkbox_id;
                    $out .= '" ';
                    $out .= 'value="1"';
                    $out .= isset($show_columns[$group][$key]) ? ' checked="checked"' : '';
                    $out .= '> ';
                    $out .= $lbl;

                    if ($key === 'adr')
                    {
                        $out .= ', split door teken: ';
                        $out .= '<input type="text" ';
                        $out .= 'name="sh[p][c][adr_split]" ';
                        $out .= 'size="1" value="';
                        $out .= $adr_split;
                        $out .= '">';
                    }

                    if ($key === 'saldo_date')
                    {
                        $out .= '<div class="input-group">';
                        $out .= '<span class="input-group-addon">';
                        $out .= '<i class="fa fa-calendar"></i>';
                        $out .= '</span>';
                        $out .= '<input type="text" ';
                        $out .= 'class="form-control" ';
                        $out .= 'name="sh[p][u][saldo_date]" ';
                        $out .= 'data-provide="datepicker" ';
                        $out .= 'data-date-format="';
                        $out .= $app['date_format']->datepicker_format($app['tschema']);
                        $out .= '" ';
                        $out .= 'data-date-language="nl" ';
                        $out .= 'data-date-today-highlight="true" ';
                        $out .= 'data-date-autoclose="true" ';
                        $out .= 'data-date-enable-on-readonly="false" ';
                        $out .= 'data-date-end-date="0d" ';
                        $out .= 'data-date-orientation="bottom" ';
                        $out .= 'placeholder="';
                        $out .= $app['date_format']->datepicker_placeholder($app['tschema']);
                        $out .= '" ';
                        $out .= 'value="';
                        $out .= $saldo_date;
                        $out .= '">';
                        $out .= '</div>';

                        $columns['u']['saldo_date'] = 'Saldo op ' . $saldo_date;
                    }

                    $out .= '</label>';
                    $out .= '</div>';
                }
            }

            $out .= '</div>';
            $out .= '<div class="row">';
            $out .= '<div class="col-md-12">';
            $out .= '<input type="submit" name="show" ';
            $out .= 'class="btn btn-default" ';
            $out .= 'value="Pas weergave kolommen aan">';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        if ($v_list || $v_tiles)
        {
            $out .= '<br>';

            $out .= '<div class="panel panel-info">';
            $out .= '<div class="panel-heading">';

            $out .= '<div class="row">';
            $out .= '<div class="col-xs-12">';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-search"></i>';
            $out .= '</span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="q" name="q" value="' . $q . '" ';
            $out .= 'placeholder="Zoeken">';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';
            $out .= '</div>';

            $out .= '</form>';

            $out .= '<div class="pull-right hidden-xs hidden-sm print-hide">';
            $out .= 'Totaal: <span id="total"></span>';
            $out .= '</div>';

            $out .= '<ul class="nav nav-tabs" id="nav-tabs">';

            $nav_params = $params;

            foreach ($st as $k => $tab)
            {
                $nav_params['status'] = $k;

                $out .= '<li';
                $out .= $status === $k ? ' class="active"' : '';
                $out .= '>';

                $class_ary = isset($tab['cl']) ? ['class' => 'bg-' . $tab['cl']] : [];

                $out .= $app['link']->link($users_route, $app['pp_ary'],
                    $nav_params, $tab['lbl'], $class_ary);

                $out .= '</li>';
            }

            $out .= '</ul>';
        }

        if ($v_list)
        {
            $out .= '<div class="panel panel-success printview">';
            $out .= '<div class="table-responsive">';

            $out .= '<table class="table table-bordered table-striped table-hover footable csv" ';
            $out .= 'data-filtering="true" data-filter-delay="0" ';
            $out .= 'data-filter="#q" data-filter-min="1" data-cascade="true" ';
            $out .= 'data-empty="Er zijn geen ';
            $out .= $app['s_admin'] ? 'gebruikers' : 'leden';
            $out .= ' volgens de selectiecriteria" ';
            $out .= 'data-sorting="true" ';
            $out .= 'data-filter-placeholder="Zoeken" ';
            $out .= 'data-filter-position="left"';

            if (count($ref_geo))
            {
                $out .= ' data-lat="' . $ref_geo['lat'] . '" ';
                $out .= 'data-lng="' . $ref_geo['lng'] . '"';
            }

            $out .= '>';
            $out .= '<thead>';

            $out .= '<tr>';

            $numeric_keys = [
                'saldo'			=> true,
                'saldo_date'	=> true,
            ];

            $date_keys = [
                'cdate'			=> true,
                'mdate'			=> true,
                'adate'			=> true,
                'lastlogin'		=> true,
            ];

            $link_user_keys = [
                'letscode'		=> true,
                'name'			=> true,
            ];

            foreach ($show_columns as $group => $ary)
            {
                if ($group === 'p')
                {
                    continue;
                }
                else if ($group === 'a')
                {
                    foreach ($ary as $a_key => $a_ary)
                    {
                        foreach ($a_ary as $key => $one)
                        {
                            $out .= '<th data-type="numeric">';
                            $out .= $columns[$group][$a_key][$key];
                            $out .= '</th>';
                        }
                    }

                    continue;
                }
                else if ($group === 'd')
                {
                    if (count($ref_geo))
                    {
                        foreach($ary as $key => $one)
                        {
                            $out .= '<th>';
                            $out .= $columns[$group][$key];
                            $out .= '</th>';
                        }
                    }

                    continue;
                }
                else if ($group === 'c')
                {
                    $tpl = '<th data-hide="tablet, phone" data-sort-ignore="true">%1$s</th>';

                    foreach ($ary as $key => $one)
                    {
                        if ($key == 'adr' && $adr_split != '')
                        {
                            $out .= sprintf($tpl, 'Adres (1)');
                            $out .= sprintf($tpl, 'Adres (2)');
                            continue;
                        }

                        $out .= sprintf($tpl, $columns[$group][$key]);
                    }

                    continue;
                }
                else if ($group === 'u')
                {
                    foreach ($ary as $key => $one)
                    {
                        $data_type =  isset($numeric_keys[$key]) ? ' data-type="numeric"' : '';
                        $data_sort_initial = $key === 'letscode' ? ' data-sort-initial="true"' : '';

                        $out .= '<th' . $data_type . $data_sort_initial . '>';
                        $out .= $columns[$group][$key];
                        $out .= '</th>';
                    }

                    continue;
                }
                else if ($group === 'm')
                {
                    foreach ($ary as $key => $one)
                    {
                        $out .= '<th data-type="numeric">';
                        $out .= $columns[$group][$key];
                        $out .= '</th>';
                    }

                    continue;
                }
            }

            $out .= '</tr>';

            $out .= '</thead>';
            $out .= '<tbody>';

            $checkbox = '<input type="checkbox" name="sel_%1$s" value="1"%2$s>&nbsp;';

            $can_link = $app['s_admin'];

            foreach($users as $u)
            {
                if (($app['s_user'] || $app['s_guest'])
                    && ($u['status'] === 1 || $u['status'] === 2))
                {
                    $can_link = true;
                }

                $id = $u['id'];

                $row_stat = ($u['status'] == 1 && $app['new_user_treshold'] < strtotime($u['adate'])) ? 3 : $u['status'];

                $first = true;

                $out .= '<tr';

                if (isset(cnst_status::CLASS_ARY[$row_stat]))
                {
                    $out .= ' class="';
                    $out .= cnst_status::CLASS_ARY[$row_stat];
                    $out .= '"';
                }

                $out .= ' data-balance="';
                $out .= $u['saldo'];
                $out .= '">';

                if (isset($show_columns['u']))
                {
                    foreach ($show_columns['u'] as $key => $one)
                    {
                        $out .= '<td';
                        $out .= isset($date_keys[$key]) ? ' data-value="' . $u[$key] . '"' : '';
                        $out .= '>';

                        $out .= $app['s_admin'] && $first ? sprintf($checkbox, $id, isset($selected_users[$id]) ? ' checked="checked"' : '') : '';
                        $first = false;

                        if (isset($link_user_keys[$key]))
                        {
                            if ($can_link)
                            {
                                $out .= $app['link']->link_no_attr('users_show', $app['pp_ary'],
                                    ['id' => $u['id']], $u[$key]);
                            }
                            else
                            {
                                $out .= htmlspecialchars($u[$key], ENT_QUOTES);
                            }
                        }
                        else if (isset($date_keys[$key]))
                        {
                            if ($u[$key])
                            {
                                $out .= $app['date_format']->get($u[$key], 'day', $app['tschema']);
                            }
                            else
                            {
                                $out .= '&nbsp;';
                            }
                        }
                        else if ($key === 'fullname')
                        {
                            if ($app['s_admin']
                                || $u['fullname_access'] === 'interlets'
                                || ($app['s_user'] && $u['fullname_access'] !== 'admin'))
                            {
                                if ($can_link)
                                {
                                    $out .= $app['link']->link_no_attr('users_show', $app['pp_ary'],
                                        ['id' => $u['id']], $u['fullname']);
                                }
                                else
                                {
                                    $out .= htmlspecialchars($u['fullname'], ENT_QUOTES);
                                }
                            }
                            else
                            {
                                $out .= '<span class="btn btn-default">';
                                $out .= 'verborgen</span>';
                            }
                        }
                        else if ($key === 'accountrole')
                        {
                            $out .= cnst_role::LABEL_ARY[$u['accountrole']];
                        }
                        else
                        {
                            $out .= htmlspecialchars($u[$key]);
                        }

                        $out .= '</td>';
                    }
                }

                if (isset($show_columns['c']))
                {
                    foreach ($show_columns['c'] as $key => $one)
                    {
                        $out .= '<td>';

                        if ($key == 'adr' && $adr_split != '')
                        {
                            if (!isset($contacts[$id][$key]))
                            {
                                $out .= '&nbsp;</td><td>&nbsp;</td>';
                                continue;
                            }

                            [$adr_1, $adr_2] = explode(trim($adr_split), $contacts[$id]['adr'][0][0]);

                            $out .= get_contacts_str([[$adr_1, $contacts[$id]['adr'][0][1]]], 'adr');
                            $out .= '</td><td>';
                            $out .= get_contacts_str([[$adr_2, $contacts[$id]['adr'][0][1]]], 'adr');
                        }
                        else if (isset($contacts[$id][$key]))
                        {
                            $out .= get_contacts_str($contacts[$id][$key], $key);
                        }
                        else
                        {
                            $out .= '&nbsp;';
                        }

                        $out .= '</td>';
                    }
                }

                if (isset($show_columns['d']) && count($ref_geo))
                {
                    $out .= '<td data-value="5000"';

                    $adr_ary = $contacts[$id]['adr'][0] ?? [];

                    if (isset($adr_ary[1]))
                    {
                        if ($adr_ary[1] >= $app['s_access_level'])
                        {
                            if (count($adr_ary) && $adr_ary[0])
                            {
                                $geo = $app['cache']->get('geo_' . $adr_ary[0]);

                                if ($geo)
                                {
                                    $out .= ' data-lat="';
                                    $out .= $geo['lat'];
                                    $out .= '" data-lng="';
                                    $out .= $geo['lng'];
                                    $out .= '"';
                                }
                            }

                            $out .= '><i class="fa fa-times"></i>';
                        }
                        else
                        {
                            $out .= '><span class="btn btn-default">verborgen</span>';
                        }
                    }
                    else
                    {
                        $out .= '><i class="fa fa-times"></i>';
                    }

                    $out .= '</td>';
                }

                if (isset($show_columns['m']))
                {
                    foreach($show_columns['m'] as $key => $one)
                    {
                        $out .= '<td>';

                        if (isset($msgs_count[$id][$key]))
                        {
                            $out .= $app['link']->link_no_attr('messages', $app['pp_ary'],
                                [
                                    'f'	=> [
                                        'uid' 	=> $id,
                                        'type' 	=> $message_type_filter[$key],
                                    ],
                                ],
                                $msgs_count[$id][$key]);
                        }

                        $out .= '</td>';
                    }
                }

                if (isset($show_columns['a']))
                {
                    $from_date = $app['date_format']->get_from_unix(time() - ($activity_days * 86400), 'day', $app['tschema']);

                    foreach($show_columns['a'] as $a_key => $a_ary)
                    {
                        foreach ($a_ary as $key => $one)
                        {
                            $out .= '<td>';

                            if (isset($activity[$id][$a_key][$key]))
                            {
                                if (isset($code_only_activity_filter_code))
                                {
                                    $out .= $activity[$id][$a_key][$key];
                                }
                                else
                                {
                                    $out .= $app['link']->link_no_attr('transactions', $app['pp_ary'],
                                        [
                                            'f' => [
                                                'fcode'	=> $key === 'in' ? '' : $u['letscode'],
                                                'tcode'	=> $key === 'out' ? '' : $u['letscode'],
                                                'andor'	=> $key === 'total' ? 'or' : 'and',
                                                'fdate' => $from_date,
                                            ],
                                        ],
                                        $activity[$id][$a_key][$key]);
                                }
                            }

                            $out .= '</td>';
                        }
                    }
                }

                $out .= '</tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';
            $out .= '</div></div>';

            $out .= '<div class="row"><div class="col-md-12">';
            $out .= '<p><span class="pull-right">Totaal saldo: <span id="sum"></span> ';
            $out .= $app['config']->get('currency', $app['tschema']);
            $out .= '</span></p>';
            $out .= '</div></div>';

            if ($app['s_admin'] & isset($show_columns['u']))
            {
                $bulk_mail_cc = $app['request']->isMethod('POST') ? $bulk_mail_cc : true;

                $inp =  '<div class="form-group">';
                $inp .=  '<label for="%5$s" class="control-label">%2$s</label>';
                $inp .= '<div class="input-group">';
                $inp .= '<span class="input-group-addon">';
                $inp .= '<span class="fa fa-%6$s"></span></span>';
                $inp .=  '<input type="%3$s" id="%5$s" name="%1$s" %4$s>';
                $inp .=  '</div>';
                $inp .=  '</div>';

                $checkbox = '<div class="form-group">';
                $checkbox .= '<label for="%5$s" class="control-label">';
                $checkbox .= '<input type="%3$s" id="%5$s" name="%1$s" %4$s>';
                $checkbox .= ' %2$s</label></div>';

                $acc_sel = '<div class="form-group">';
                $acc_sel .= '<label for="%1$s" class="control-label">';
                $acc_sel .= '%2$s</label>';
                $acc_sel .= '<div class="input-group">';
                $acc_sel .= '<span class="input-group-addon">';
                $acc_sel .= '<span class="fa fa-%4$s"></span></span>';
                $acc_sel .= '<select name="%1$s" id="%1$s" class="form-control">';
                $acc_sel .= '%3$s';
                $acc_sel .= '</select>';
                $acc_sel .= '</div>';
                $acc_sel .= '</div>';

                $out .= '<div class="panel panel-default" id="actions">';
                $out .= '<div class="panel-heading">';

                $out .= '<span class="btn btn-default" id="invert_selection">';
                $out .= 'Selectie omkeren</span>&nbsp;';
                $out .= '<span class="btn btn-default" id="select_all">';
                $out .= 'Selecteer alle</span>&nbsp;';
                $out .= '<span class="btn btn-default" id="deselect_all">';
                $out .= 'De-selecteer alle</span>';

                $out .= '</div>';
                $out .= '</div>';

                $out .= '<h3>Bulk acties met geselecteerde gebruikers</h3>';
                $out .= '<div class="panel panel-info">';
                $out .= '<div class="panel-heading">';

                $out .= '<ul class="nav nav-tabs" role="tablist">';

                $out .= '<li class="active">';
                $out .= '<a href="#mail_tab" data-toggle="tab">Mail</a></li>';
                $out .= '<li class="dropdown">';

                $out .= '<a class="dropdown-toggle" data-toggle="dropdown" href="#">Veld aanpassen';
                $out .= '<span class="caret"></span></a>';
                $out .= '<ul class="dropdown-menu">';

                foreach (self::get_edit_fields_tabs() as $k => $t)
                {
                    $out .= '<li>';
                    $out .= '<a href="#' . $k . '_tab" data-toggle="tab">';
                    $out .= $t['lbl'];
                    $out .= '</a></li>';
                }

                $out .= '</ul>';
                $out .= '</li>';
                $out .= '</ul>';

                $out .= '<div class="tab-content">';

                $out .= '<div role="tabpanel" class="tab-pane active" id="mail_tab">';
                $out .= '<h3>E-Mail verzenden naar geselecteerde gebruikers</h3>';

                $out .= '<form method="post">';

                $out .= '<div class="form-group">';
                $out .= '<input type="text" class="form-control" id="bulk_mail_subject" name="bulk_mail_subject" ';
                $out .= 'placeholder="Onderwerp" ';
                $out .= 'value="';
                $out .= $bulk_mail_subject ?? '';
                $out .= '" required>';
                $out .= '</div>';

                $out .= '<div class="form-group">';
                $out .= '<textarea name="bulk_mail_content" ';
                $out .= 'class="form-control rich-edit" ';
                $out .= 'id="bulk_mail_content" rows="8" ';
                $out .= 'data-template-vars="';
                $out .= implode(',', array_keys($map_template_vars));
                $out .= '" ';
                $out .= 'required>';
                $out .= $bulk_mail_content ?? '';
                $out .= '</textarea>';
                $out .= '</div>';

                $out .= '<div class="form-group">';
                $out .= '<label for="bulk_mail_cc" class="control-label">';
                $out .= '<input type="checkbox" name="bulk_mail_cc" ';
                $out .= 'id="bulk_mail_cc"';
                $out .= $bulk_mail_cc ? ' checked="checked"' : '';
                $out .= ' value="1" > ';
                $out .= 'Stuur een kopie met verzendinfo naar mijzelf';
                $out .= '</label>';
                $out .= '</div>';

                $out .= '<div class="form-group">';
                $out .= '<label for="verify_mail" class="control-label">';
                $out .= '<input type="checkbox" name="verify_mail" ';
                $out .= 'id="verify_mail" ';
                $out .= 'value="1" required> ';
                $out .= 'Ik heb mijn bericht nagelezen en nagekeken dat de juiste gebruikers geselecteerd zijn.';
                $out .= '</label>';
                $out .= '</div>';

                $out .= '<input type="submit" value="Zend test E-mail naar jezelf" name="bulk_mail_test" class="btn btn-default">&nbsp;';
                $out .= '<input type="submit" value="Verzend" name="bulk_mail_submit" class="btn btn-default">';

                $out .= $app['form_token']->get_hidden_input();
                $out .= '</form>';
                $out .= '</div>';

                foreach(self::get_edit_fields_tabs() as $k => $t)
                {
                    $out .= '<div role="tabpanel" class="tab-pane" id="';
                    $out .= $k;
                    $out .= '_tab"';
                    $out .= isset($t['item_access']) ? ' data-access-control="true"' : '';
                    $out .= '>';
                    $out .= '<h3>Veld aanpassen: ' . $t['lbl'] . '</h3>';

                    $out .= '<form method="post">';

                    if (isset($t['options']))
                    {
                        $options = $t['options'];
                        $out .= sprintf($acc_sel,
                            $k,
                            $t['lbl'],
                            $app['select']->get_options($options, 0),
                            $t['fa']);
                    }
                    else if (isset($t['type']) && $t['type'] == 'checkbox')
                    {
                        $out .= sprintf($checkbox, $k, $t['lbl'], $t['type'], 'value="1"', $k);
                    }
                    else if (isset($t['item_access']))
                    {
                        $out .= $app['item_access']->get_radio_buttons('access');
                    }
                    else
                    {
                        $out .= sprintf($inp, $k, $t['lbl'], $t['type'], 'class="form-control"', $k, $t['fa']);
                    }

                    $out .= '<div class="form-group">';
                    $out .= '<label for="verify_' . $k . '" class="control-label">';
                    $out .= '<input type="checkbox" name="verify_' . $k . '" ';
                    $out .= 'id="verify_' . $k . '" ';
                    $out .= 'value="1" required> ';
                    $out .= 'Ik heb nagekeken dat de juiste gebruikers geselecteerd zijn en veld en ingevulde waarde nagekeken.';
                    $out .= '</label>';
                    $out .= '</div>';

                    $out .= '<input type="hidden" value="' . $k . '" name="bulk_field">';
                    $out .= '<input type="submit" value="Veld aanpassen" name="' . $k . '_bulk_submit" class="btn btn-primary">';
                    $out .= $app['form_token']->get_hidden_input();
                    $out .= '</form>';

                    $out .= '</div>';
                }

                $out .= '<div class="clearfix"></div>';
                $out .= '</div>';
                $out .= '</div>';
                $out .= '</div>';
            }
        }

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get($request);
    }

    static public function btn_nav(
        btn_nav $btn_nav,
        array $pp_ary,
        array $params,
        string $matched_route
    ):void
    {
        $admin_suffix = $pp_ary['role_short'] === 'a' ? '_admin' : '';

        $btn_nav->view('users_list' . $admin_suffix, $pp_ary,
            $params, 'Lijst', 'align-justify',
            $matched_route === 'users_list');

        $btn_nav->view('users_tiles' . $admin_suffix, $pp_ary,
            $params, 'Tegels met foto\'s', 'th',
            $matched_route === 'users_tiles');

        unset($params['status']);

        $btn_nav->view('users_map', $pp_ary,
            $params, 'Kaart', 'map-marker',
            $matched_route === 'users_map');
    }

    static public function heading(heading $heading):void
    {
        $heading->add('Gebruikers');
        $heading->fa('users');
    }

    static public function get_st(bool $s_admin, int $new_user_treshold):array
    {
        $st = [
            'active'	=> [
                'lbl'	=> $s_admin ? 'Actief' : 'Alle',
                'sql'	=> 'u.status in (1, 2)',
                'st'	=> [1, 2],
            ],
            'new'		=> [
                'lbl'	=> 'Instappers',
                'sql'	=> 'u.status = 1 and u.adate > ?',
                'sql_bind'	=> gmdate('Y-m-d H:i:s', $new_user_treshold),
                'cl'	=> 'success',
                'st'	=> 3,
            ],
            'leaving'	=> [
                'lbl'	=> 'Uitstappers',
                'sql'	=> 'u.status = 2',
                'cl'	=> 'danger',
                'st'	=> 2,
            ],
        ];

        if ($s_admin)
        {
            $st = $st + [
                'inactive'	=> [
                    'lbl'	=> 'Inactief',
                    'sql'	=> 'u.status = 0',
                    'cl'	=> 'inactive',
                    'st'	=> 0,
                ],
                'ip'		=> [
                    'lbl'	=> 'Info-pakket',
                    'sql'	=> 'u.status = 5',
                    'cl'	=> 'warning',
                    'st'	=> 5,
                ],
                'im'		=> [
                    'lbl'	=> 'Info-moment',
                    'sql'	=> 'u.status = 6',
                    'cl'	=> 'info',
                    'st'	=> 6
                ],
                'extern'	=> [
                    'lbl'	=> 'Extern',
                    'sql'	=> 'u.status = 7',
                    'cl'	=> 'extern',
                    'st'	=> 7,
                ],
                'all'		=> [
                    'lbl'	=> 'Alle',
                    'sql'	=> '1 = 1',
                ],
            ];
        }

        return $st;
    }

    static public function get_edit_fields_tabs():array
    {
        return [
            'fullname_access'	=> [
                'lbl'				=> 'Zichtbaarheid Volledige Naam',
                'item_access'	=> true,
            ],
            'adr_access'		=> [
                'lbl'		=> 'Zichtbaarheid adres',
                'item_access'	=> true,
            ],
            'mail_access'		=> [
                'lbl'		=> 'Zichtbaarheid E-mail adres',
                'item_access'	=> true,
            ],
            'tel_access'		=> [
                'lbl'		=> 'Zichtbaarheid telefoonnummer',
                'item_access'	=> true,
            ],
            'gsm_access'		=> [
                'lbl'		=> 'Zichtbaarheid GSM-nummer',
                'item_access'	=> true,
            ],
            'comments'			=> [
                'lbl'		=> 'Commentaar',
                'type'		=> 'text',
                'string'	=> true,
                'fa'		=> 'comment-o',
            ],
            'accountrole'		=> [
                'lbl'		=> 'Rechten',
                'options'	=> cnst_role::LABEL_ARY,
                'string'	=> true,
                'fa'		=> 'hand-paper-o',
            ],
            'status'			=> [
                'lbl'		=> 'Status',
                'options'	=> cnst_status::LABEL_ARY,
                'fa'		=> 'star-o',
            ],
            'admincomment'		=> [
                'lbl'		=> 'Commentaar van de Admin',
                'type'		=> 'text',
                'string'	=> true,
                'fa'		=> 'comment-o',
            ],
            'minlimit'			=> [
                'lbl'		=> 'Minimum Account Limiet',
                'type'		=> 'number',
                'fa'		=> 'arrow-down',
            ],
            'maxlimit'			=> [
                'lbl'		=> 'Maximum Account Limiet',
                'type'		=> 'number',
                'fa'		=> 'arrow-up',
            ],
            'cron_saldo'		=> [
                'lbl'	=> 'Periodieke Overzichts E-mail (aan/uit)',
                'type'	=> 'checkbox',
            ],
        ];
    }
}