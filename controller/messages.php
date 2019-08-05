<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class messages
{
    static public function fetch(Request $request, app $app):array
    {

    }

    public function list(Request $request, app $app):Response
    {
        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);
        $inline_en = $request->query->get('inline', false) ? true : false;

        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && isset($filter['uid'])
            && $app['s_id'] == $filter['uid']
            && $app['s_id'];

        $view = 'list';
        $inline_en = $app['request']->query->get('inline') ? true : false;

        $v_list = $view === 'list' || $inline_en;
        $v_extended = $view === 'extended' && !$inline_en;

        $params = [
            's'	=> [
                'orderby'	=> $sort['orderby'] ?? 'm.cdate',
                'asc'		=> $sort['asc'] ?? 0,
            ],
            'p'	=> [
                'start'		=> $pag['start'] ?? 0,
                'limit'		=> $pag['limit'] ?? 25,
            ],
        ];

        $params_sql = $where_sql = $ustatus_sql = [];

        if (isset($filter['uid'])
            && $filter['uid']
            && !isset($filter['s']))
        {
            $filter['fcode'] = $app['account']->str($filter['uid'], $app['tschema']);
        }

        if (isset($filter['uid']))
        {
            $params['f']['uid'] = $filter['uid'];
        }

        if (isset($filter['q'])
            && $filter['q'])
        {
            $where_sql[] = '(m.content ilike ? or m."Description" ilike ?)';
            $params_sql[] = '%' . $filter['q'] . '%';
            $params_sql[] = '%' . $filter['q'] . '%';
            $params['f']['q'] = $filter['q'];
        }

        if (isset($filter['fcode'])
            && $filter['fcode'] !== '')
        {
            [$fcode] = explode(' ', trim($filter['fcode']));
            $fcode = trim($fcode);

            $fuid = $app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.users
                where letscode = ?', [$fcode]);

            if ($fuid)
            {
                $where_sql[] = 'u.id = ?';
                $params_sql[] = $fuid;

                $fcode = $app['account']->str($fuid, $app['tschema']);
                $params['f']['fcode'] = $fcode;
            }
            else
            {
                $where_sql[] = '1 = 2';
            }
        }

        if (isset($filter['cid'])
            && $filter['cid'])
        {
            $cat_ary = [];

            $st = $app['db']->prepare('select id
                from ' . $app['tschema'] . '.categories
                where id_parent = ?');
            $st->bindValue(1, $filter['cid']);
            $st->execute();

            while ($row = $st->fetch())
            {
                $cat_ary[] = $row['id'];
            }

            if (count($cat_ary))
            {
                $where_sql[] = 'm.id_category in (' . implode(', ', $cat_ary) . ')';
            }
            else
            {
                $where_sql[] = 'm.id_category = ?';
                $params_sql[] = $filter['cid'];
            }

            $params['f']['cid'] = $filter['cid'];
        }

        $filter_valid = isset($filter['valid'])
            && (isset($filter['valid']['yes']) xor isset($filter['valid']['no']));

        if ($filter_valid)
        {
            if (isset($filter['valid']['yes']))
            {
                $where_sql[] = 'm.validity >= now()';
                $params['f']['valid']['yes'] = 'on';
            }
            else
            {
                $where_sql[] = 'm.validity < now()';
                $params['f']['valid']['no'] = 'on';
            }
        }

        $filter_type = isset($filter['type'])
            && (isset($filter['type']['want']) xor isset($filter['type']['offer']));

        if ($filter_type)
        {
            if (isset($filter['type']['want']))
            {
                $where_sql[] = 'm.msg_type = 0';
                $params['f']['type']['want'] = 'on';
            }
            else
            {
                $where_sql[] = 'm.msg_type = 1';
                $params['f']['type']['offer'] = 'on';
            }
        }

        $filter_ustatus = isset($filter['ustatus']) &&
            !(isset($filter['ustatus']['new'])
                && isset($filter['ustatus']['leaving'])
                && isset($filter['ustatus']['active']));

        if ($filter_ustatus)
        {
            if (isset($filter['ustatus']['new']))
            {
                $ustatus_sql[] = '(u.adate > ? and u.status = 1)';
                $params_sql[] = gmdate('Y-m-d H:i:s', $app['new_user_treshold']);
                $params['f']['ustatus']['new'] = 'on';
            }

            if (isset($filter['ustatus']['leaving']))
            {
                $ustatus_sql[] = 'u.status = 2';
                $params['f']['ustatus']['leaving'] = 'on';
            }

            if (isset($filter['ustatus']['active']))
            {
                $ustatus_sql[] = '(u.adate <= ? and u.status = 1)';
                $params_sql[] = gmdate('Y-m-d H:i:s', $app['new_user_treshold']);
                $params['f']['ustatus']['active'] = 'on';
            }

            if (count($ustatus_sql))
            {
                $where_sql[] = '(' . implode(' or ', $ustatus_sql) . ')';
            }
        }

        if ($app['s_guest'])
        {
            $where_sql[] = 'm.local = \'f\'';
        }

        if (count($where_sql))
        {
            $where_sql = ' and ' . implode(' and ', $where_sql) . ' ';
        }
        else
        {
            $where_sql = '';
        }

        $query = 'select m.*, u.postcode
            from ' . $app['tschema'] . '.messages m, ' .
                $app['tschema'] . '.users u
                where m.id_user = u.id' . $where_sql . '
            order by ' . $params['s']['orderby'] . ' ';

        $row_count = $app['db']->fetchColumn('select count(m.*)
            from ' . $app['tschema'] . '.messages m, ' .
                $app['tschema'] . '.users u
            where m.id_user = u.id' . $where_sql, $params_sql);

        $query .= $params['s']['asc'] ? 'asc ' : 'desc ';
        $query .= ' limit ' . $params['p']['limit'];
        $query .= ' offset ' . $params['p']['start'];

        $messages = $app['db']->fetchAll($query, $params_sql);

        if ($v_extended)
        {
            $ids = $imgs = [];

            foreach ($messages as $msg)
            {
                $ids[] = $msg['id'];
            }

            $_imgs = $app['db']->executeQuery('select mp.msgid, mp."PictureFile"
                from ' . $app['tschema'] . '.msgpictures mp
                where msgid in (?)',
                [$ids],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

            foreach ($_imgs as $_img)
            {
                if (isset($imgs[$_img['msgid']]))
                {
                    continue;
                }

                $imgs[$_img['msgid']] = $_img['PictureFile'];
            }
        }

        $app['pagination']->init($app['r_messages'], $app['pp_ary'],
            $row_count, $params, $inline_en);

        $asc_preset_ary = [
            'asc'	=> 0,
            'fa' 	=> 'sort',
        ];

        $tableheader_ary = [
            'm.msg_type' => array_merge($asc_preset_ary, [
                'lbl' => 'V/A']),
            'm.content' => array_merge($asc_preset_ary, [
                'lbl' => 'Wat']),
        ];

        if (!isset($filter['uid']))
        {
            $tableheader_ary += [
                'u.name'	=> array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Wie',
                    'data_hide' => 'phone,tablet',
                ]),
                'u.postcode'	=> array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Postcode',
                    'data_hide'	=> 'phone,tablet',
                ]),
            ];
        }

        if (!($filter['cid'] ?? false))
        {
            $tableheader_ary += [
                'm.id_category' => array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Categorie',
                    'data_hide'	=> 'phone, tablet',
                ]),
            ];
        }

        $tableheader_ary += [
            'm.validity' => array_merge($asc_preset_ary, [
                'lbl' 	=> 'Geldig tot',
                'data_hide'	=> 'phone, tablet',
            ]),
        ];

        if (!$app['s_guest'] && $app['intersystems']->get_count($app['tschema']))
        {
            $tableheader_ary += [
                'm.local' => array_merge($asc_preset_ary, [
                    'lbl' 	=> 'Zichtbaarheid',
                    'data_hide'	=> 'phone, tablet',
                ]),
            ];
        }

        $tableheader_ary[$params['s']['orderby']]['asc']
            = $params['s']['asc'] ? 0 : 1;
        $tableheader_ary[$params['s']['orderby']]['fa']
            = $params['s']['asc'] ? 'sort-asc' : 'sort-desc';

        unset($tableheader_ary['m.cdate']);

        $cats = ['' => '-- alle categorieën --'];

        $categories = $cat_params  = [];

        if (isset($filter['uid']))
        {
            $st = $app['db']->executeQuery('select c.*
                from ' . $app['tschema'] . '.categories c, ' .
                    $app['tschema'] . '.messages m
                where m.id_category = c.id
                    and m.id_user = ?
                order by c.fullname', [$filter['uid']]);
        }
        else
        {
            $st = $app['db']->executeQuery('select *
                from ' . $app['tschema'] . '.categories
                order by fullname');
        }

        while ($row = $st->fetch())
        {
            $cats[$row['id']] = $row['id_parent'] ? ' . . ' : '';
            $cats[$row['id']] .= $row['name'];
            $count_msgs = $row['stat_msgs_offers'] + $row['stat_msgs_wanted'];

            if ($row['id_parent'] && $count_msgs)
            {
                $cats[$row['id']] .= ' (' . $count_msgs . ')';
            }

            $categories[$row['id']] = $row['fullname'];

            $cat_params[$row['id']] = $params;
            $cat_params[$row['id']]['f']['cid'] = $row['id'];
        }

        if ($app['s_admin'] || $app['s_user'])
        {
            if (!$inline_en
                && ($s_owner || !isset($filter['uid'])))
            {
                $app['btn_top']->add('messages_add', $app['pp_ary'],
                    [], 'Vraag of aanbod toevoegen');
            }

            if (isset($filter['uid']))
            {
                if ($app['s_admin'] && !$s_owner)
                {
                    $str = 'Vraag of aanbod voor ';
                    $str .= $app['account']->str($filter['uid'], $app['tschema']);

                    $app['btn_top']->add('messages_add', $app['pp_ary'],
                        ['uid' => $filter['uid']], $str);
                }
            }
        }

        if ($app['s_admin'] && $v_list)
        {
            $app['btn_nav']->csv();
        }

        $filter_panel_open = (($filter['fcode'] ?? false) && !isset($filter['uid']))
            || $filter_type
            || $filter_valid
            || $filter_ustatus;

        $filtered = ($filter['q'] ?? false) || $filter_panel_open;

        if (isset($filter['uid']))
        {
            if ($s_owner && !$inline_en)
            {
                $app['heading']->add('Mijn vraag en aanbod');
            }
            else
            {
                $app['heading']->add($app['link']->link_no_attr($app['r_messages'], $app['pp_ary'],
                    ['f' => ['uid' => $filter['uid']]],
                    'Vraag en aanbod'));

                $app['heading']->add(' van ');
                $app['heading']->add($app['account']->link($filter['uid'], $app['pp_ary']));
            }
        }
        else
        {
            $app['heading']->add('Vraag en aanbod');
        }

        if (isset($filter['cid']) && $filter['cid'])
        {
            $app['heading']->add(', categorie "' . $categories[$filter['cid']] . '"');
        }

        $app['heading']->add_filtered($filtered);
        $app['heading']->fa('newspaper-o');

        if (!$inline_en)
        {
            $app['btn_nav']->view('messages_list', $app['pp_ary'],
                $params, 'Lijst', 'align-justify', true);

            $app['btn_nav']->view('messages_extended', $app['pp_ary'],
                $params, 'Lijst met omschrijvingen', 'th-list', false);

            $app['assets']->add(['msgs.js', 'table_sel.js']);

            $out = '<div class="panel panel-info">';
            $out .= '<div class="panel-heading">';

            $out .= '<form method="get" class="form-horizontal">';

            $out .= '<div class="row">';

            $out .= '<div class="col-sm-5">';
            $out .= '<div class="input-group margin-bottom">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-search"></i>';
            $out .= '</span>';
            $out .= '<input type="text" class="form-control" id="q" value="';
            $out .= $filter['q'] ?? '';
            $out .= '" name="f[q]" placeholder="Zoeken">';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="col-sm-5 col-xs-10">';
            $out .= '<div class="input-group margin-bottom">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-clone"></i>';
            $out .= '</span>';
            $out .= '<select class="form-control" id="cid" name="f[cid]">';

            $out .= $app['select']->get_options($cats, $filter['cid'] ?? 0);

            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="col-sm-2 col-xs-2">';
            $out .= '<button class="btn btn-default btn-block" title="Meer filters" ';
            $out .= 'type="button" ';
            $out .= 'data-toggle="collapse" data-target="#filters">';
            $out .= '<i class="fa fa-caret-down"></i><span class="hidden-xs hidden-sm"> ';
            $out .= 'Meer</span></button>';
            $out .= '</div>';

            $out .= '</div>';

            $out .= '<div id="filters"';
            $out .= $filter_panel_open ? '' : ' class="collapse"';
            $out .= '>';

            $out .= '<div class="row">';

            $offerwant_options = [
                'want'		=> 'Vraag',
                'offer'		=> 'Aanbod',
            ];

            $out .= '<div class="col-md-12">';
            $out .= '<div class="input-group margin-bottom">';

            $out .= self::get_checkbox_filter($offerwant_options, 'type', $filter);

            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';
            $out .= '<div class="row">';

            $valid_options = [
                'yes'		=> 'Geldig',
                'no'		=> 'Vervallen',
            ];

            $out .= '<div class="col-md-12">';
            $out .= '<div class="input-group margin-bottom">';

            $out .= self::get_checkbox_filter($valid_options, 'valid', $filter);

            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';
            $out .= '<div class="row">';

            $user_status_options = [
                'active'	=> 'Niet in- of uitstappers',
                'new'		=> 'Instappers',
                'leaving'	=> 'Uitstappers',
            ];

            $out .= '<div class="col-md-12">';
            $out .= '<div class="input-group margin-bottom">';

            $out .= self::get_checkbox_filter($user_status_options, 'ustatus', $filter);

            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';

            $out .= '<div class="row">';

            $out .= '<div class="col-sm-10">';
            $out .= '<div class="input-group margin-bottom">';
            $out .= '<span class="input-group-addon" id="fcode_addon">Van ';
            $out .= '<span class="fa fa-user"></span></span>';

            $out .= '<input type="text" class="form-control" ';
            $out .= 'aria-describedby="fcode_addon" ';
            $out .= 'data-typeahead="';

            $out .= $app['typeahead']->ini($app['pp_ary'])
                ->add('accounts', ['status'	=> 'active'])
                ->str([
                    'filter'		=> 'accounts',
                    'newuserdays'	=> $app['config']->get('newuserdays', $app['tschema']),
                ]);

            $out .= '" ';
            $out .= 'name="f[fcode]" id="fcode" placeholder="Account" ';
            $out .= 'value="';
            $out .= $filter['fcode'] ?? '';
            $out .= '">';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="col-sm-2">';
            $out .= '<input type="submit" id="filter_submit" ';
            $out .= 'value="Toon" class="btn btn-default btn-block" ';
            $out .= 'name="f[s]">';
            $out .= '</div>';

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

        if ($inline_en)
        {
            $out .= '<div class="row">';
            $out .= '<div class="col-md-12">';

            $app['heading']->add_inline_btn($app['btn_top']->get());
            $out .= $app['heading']->get_h3();
        }

        $out .= $app['pagination']->get();

        if (!count($messages))
        {
            $out .= '<br>';
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-body">';
            $out .= '<p>Er zijn geen resultaten.</p>';
            $out .= '</div></div>';

            $out .= $app['pagination']->get();

            if (!$inline_en)
            {
                include __DIR__ . '/../include/footer.php';
            }
            exit;
        }

        if ($v_list)
        {
            $out .= '<div class="panel panel-info printview">';

            $out .= '<div class="table-responsive">';
            $out .= '<table class="table table-striped ';
            $out .= 'table-bordered table-hover footable csv" ';
            $out .= 'id="msgs" data-sort="false">';

            $out .= '<thead>';
            $out .= '<tr>';

            $th_params = $params;

            foreach ($tableheader_ary as $key_orderby => $data)
            {
                $out .= '<th';

                if (isset($data['data_hide']))
                {
                    $out .= ' data-hide="' . $data['data_hide'] . '"';
                }

                $out .= '>';

                if (isset($data['no_sort']))
                {
                    $out .= $data['lbl'];
                }
                else
                {
                    $th_params['s'] = [
                        'orderby'	=> $key_orderby,
                        'asc' 		=> $data['asc'],
                    ];

                    $out .= $app['link']->link_fa($app['r_messages'], $app['pp_ary'],
                        $th_params, $data['lbl'], [], $data['fa']);
                }
                $out .= '</th>';
            }

            $out .= '</tr>';
            $out .= '</thead>';

            $out .= '<tbody>';

            foreach($messages as $msg)
            {
                $out .= '<tr';
                $out .= strtotime($msg['validity']) < time() ? ' class="danger"' : '';
                $out .= '>';

                $out .= '<td>';

                if (!$inline_en && ($app['s_admin'] || $s_owner))
                {
                    $out .= '<label>';
                    $out .= '<input type="checkbox" name="sel[';
                    $out .= $msg['id'] . ']" value="1"';
                    $out .= isset($selected_msgs[$id]) ? ' checked="checked"' : '';
                    $out .= '>&nbsp;';
                    $out .= $msg['msg_type'] ? 'Aanbod' : 'Vraag';
                    $out .= '</label>';
                }
                else
                {
                    $out .= $msg['msg_type'] ? 'Aanbod' : 'Vraag';
                }

                $out .= '</td>';

                $out .= '<td>';

                $out .= $app['link']->link_no_attr('messages_show', $app['pp_ary'],
                    ['id' => $msg['id']], $msg['content']);

                $out .= '</td>';

                if (!isset($filter['uid']))
                {
                    $out .= '<td>';
                    $out .= $app['account']->link($msg['id_user'], $app['pp_ary']);
                    $out .= '</td>';

                    $out .= '<td>';
                    $out .= $msg['postcode'] ?? '';
                    $out .= '</td>';
                }

                if (!($filter['cid'] ?? false))
                {
                    $out .= '<td>';
                    $out .= $app['link']->link_no_attr($app['r_messages'], $app['pp_ary'],
                        $cat_params[$msg['id_category']],
                        $categories[$msg['id_category']]);
                    $out .= '</td>';
                }

                $out .= '<td>';
                $out .= $app['date_format']->get($msg['validity'], 'day', $app['tschema']);
                $out .= '</td>';

                if (!$app['s_guest'] && $app['intersystems']->get_count($app['tschema']))
                {
                    $out .= '<td>';
                    $out .= $app['item_access']->get_label($msg['local'] ? 'user' : 'guest');
                    $out .= '</td>';
                }

                $out .= '</tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';

            $out .= '</div>';
            $out .= '</div>';
        }
        else if ($v_extended)
        {
            $time = time();

            foreach ($messages as $msg)
            {
                $type_str = ($msg['msg_type']) ? 'Aanbod' : 'Vraag';

                $sf_owner = $app['s_system_self']
                    && $msg['id_user'] === $app['s_id'];

                $exp = strtotime($msg['validity']) < $time;

                $out .= '<div class="panel panel-info printview">';
                $out .= '<div class="panel-body';
                $out .= ($exp) ? ' bg-danger' : '';
                $out .= '">';

                $out .= '<div class="media">';

                if (isset($imgs[$msg['id']]))
                {
                    $out .= '<div class="media-left">';
                    $out .= '<a href="';

                    $out .= $app['link']->context_path('messages_show', $app['pp_ary'],
                        ['id' => $msg['id']]);

                    $out .= '">';
                    $out .= '<img class="media-object" src="';
                    $out .= $app['s3_url'] . $imgs[$msg['id']];
                    $out .= '" width="150">';
                    $out .= '</a>';
                    $out .= '</div>';
                }

                $out .= '<div class="media-body">';
                $out .= '<h3 class="media-heading">';

                $out .= $app['link']->link_no_attr('messages_show', $app['pp_ary'],
                    ['id' => $msg['id']], $type_str . ': ' . $msg['content']);

                if ($exp)
                {
                    $out .= ' <small><span class="text-danger">';
                    $out .= 'Vervallen</span></small>';
                }

                $out .= '</h3>';

                $out .= htmlspecialchars($msg['Description'], ENT_QUOTES);

                $out .= '</div>';
                $out .= '</div>';

                $out .= '</div>';

                $out .= '<div class="panel-footer">';
                $out .= '<p><i class="fa fa-user"></i> ';
                $out .= $app['account']->link($msg['id_user'], $app['pp_ary']);
                $out .= $msg['postcode'] ? ', postcode: ' . $msg['postcode'] : '';

                if ($app['s_admin'] || $sf_owner)
                {
                    $out .= '<span class="inline-buttons pull-right hidden-xs">';

                    $out .= $app['link']->link_fa('messages_edit', $app['pp_ary'],
                        ['id' => $msg['id']], 'Aanpassen',
                        ['class'	=> 'btn btn-primary btn-xs'],
                        'pencil');

                    $out .= $app['link']->link_fa('messages_del', $app['pp_ary'],
                        ['id' => $msg['id']], 'Verwijderen',
                        ['class' => 'btn btn-danger btn-xs'],
                        'times');

                    $out .= '</span>';
                }
                $out .= '</p>';
                $out .= '</div>';

                $out .= '</div>';
            }
        }

        $out .= $app['pagination']->get();

        if ($inline_en)
        {
            $out .= '</div></div>';
        }
        else if ($v_list)
        {
            if (($app['s_admin'] || $s_owner) && count($messages))
            {
                $extend_options = [
                    '7'		=> '1 week',
                    '14'	=> '2 weken',
                    '30'	=> '1 maand',
                    '60'	=> '2 maanden',
                    '180'	=> '6 maanden',
                    '365'	=> '1 jaar',
                    '730'	=> '2 jaar',
                    '1825'	=> '5 jaar',
                ];

                $out .= '<div class="panel panel-default" id="actions">';
                $out .= '<div class="panel-heading">';
                $out .= '<span class="btn btn-default" id="invert_selection">';
                $out .= 'Selectie omkeren</span>&nbsp;';
                $out .= '<span class="btn btn-default" id="select_all">';
                $out .= 'Selecteer alle</span>&nbsp;';
                $out .= '<span class="btn btn-default" id="deselect_all">';
                $out .= 'De-selecteer alle</span>';
                $out .= '</div></div>';

                $out .= '<h3>Bulk acties met geselecteerd vraag en aanbod</h3>';

                $out .= '<div class="panel panel-info">';
                $out .= '<div class="panel-heading">';

                $out .= '<ul class="nav nav-tabs" role="tablist">';
                $out .= '<li class="active"><a href="#extend_tab" ';
                $out .= 'data-toggle="tab">Verlengen</a></li>';

                if ($app['intersystem_en'])
                {
                    $out .= '<li>';
                    $out .= '<a href="#access_tab" data-toggle="tab">';
                    $out .= 'Zichtbaarheid</a><li>';
                }

                $out .= '</ul>';

                $out .= '<div class="tab-content">';

                $out .= '<div role="tabpanel" class="tab-pane active" id="extend_tab">';
                $out .= '<h3>Vraag en aanbod verlengen</h3>';

                $out .= '<form method="post" class="form-horizontal">';

                $out .= '<div class="form-group">';
                $out .= '<label for="extend" class="col-sm-2 control-label">';
                $out .= 'Verlengen met</label>';
                $out .= '<div class="col-sm-10">';
                $out .= '<select name="extend" id="extend" class="form-control">';
                $out .= $app['select']->get_options($extend_options, '30');
                $out .= "</select>";
                $out .= '</div>';
                $out .= '</div>';

                $out .= '<input type="submit" value="Verlengen" ';
                $out .= 'name="extend_submit" class="btn btn-primary">';

                $out .= $app['form_token']->get_hidden_input();

                $out .= '</form>';

                $out .= '</div>';

                if ($app['intersystem_en'])
                {
                    $out .= '<div role="tabpanel" class="tab-pane" id="access_tab">';
                    $out .= '<h3>Zichtbaarheid instellen</h3>';
                    $out .= '<form method="post">';

                    $out .= $app['item_access']->get_radio_buttons('access', '', '', true);

                    $out .= '<input type="submit" value="Aanpassen" ';
                    $out .= 'name="access_submit" class="btn btn-primary">';
                    $out .= $app['form_token']->get_hidden_input();
                    $out .= '</form>';
                    $out .= '</div>';
                }

                $out .= '</div>';

                $out .= '<div class="clearfix"></div>';
                $out .= '</div>';

                $out .= '</div></div>';
            }
        }

        $app['tpl']->add($out);
        $app['tpl']->menu('messages');

        return $app['tpl']->get($request);
    }

    static public function btn_extend(
        link $link,
        array $pp_ary,
        int $id,
        int $days,
        string $label
    ):string
    {
        return $link->link('messages_extend', $pp_ary,
            [
                'id' 		=> $id,
                'extend' 	=> $days,
            ],
            $label,
            [
                'class' => 'btn btn-default',
            ]
        );
    }

    static public function get_radio(
        array $radio_ary,
        string $name,
        string $selected,
        bool $required):string
    {
        $out = '';

        foreach ($radio_ary as $value => $label)
        {
            $out .= '<label class="radio-inline">';
            $out .= '<input type="radio" name="' . $name . '" ';
            $out .= 'value="' . $value . '"';
            $out .= (string) $value === $selected ? ' checked' : '';
            $out .= $required ? ' required' : '';
            $out .= '>&nbsp;';
            $out .= '<span class="btn btn-default">';
            $out .= $label;
            $out .= '</span>';
            $out .= '</label>';
        }

        return $out;
    }

    public static function get_checkbox_filter(
        array $checkbox_ary,
        string $filter_id,
        array $filter_ary):string
    {
        $out = '';

        foreach ($checkbox_ary as $key => $label)
        {
            $id = 'f_' . $filter_id . '_' . $key;
            $out .= '<label class="checkbox-inline" for="' . $id . '">';
            $out .= '<input type="checkbox" id="' . $id . '" ';
            $out .= 'name="f[' . $filter_id . '][' . $key . ']"';
            $out .= isset($filter_ary[$filter_id][$key]) ? ' checked' : '';
            $out .= '>&nbsp;';
            $out .= '<span class="btn btn-default">';
            $out .= $label;
            $out .= '</span>';
            $out .= '</label>';
        }

        return $out;
    }
}
