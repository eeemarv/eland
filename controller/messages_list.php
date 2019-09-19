<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use render\btn_nav;
use cnst\access as cnst_access;
use cnst\message_type as cnst_message_type;
use cnst\bulk as cnst_bulk;
use controller\messages_show;
use controller\messages_edit;

class messages_list
{
    public function messages_list(Request $request, app $app):Response
    {
        $selected_messages = $request->request->get('sel', []);
        $bulk_field = $request->request->get('bulk_field', []);
        $bulk_verify = $request->request->get('bulk_verify', []);
        $bulk_submit = $request->request->get('bulk_submit', []);

        if ($request->isMethod('POST')
            && !$app['pp_guest']
            && count($bulk_submit))
        {
            $errors = [];

            if (count($bulk_submit) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Meer dan 1 submit.');
            }

            if (count($bulk_field) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Request voor meer dan één veld.');
            }

            if (count($bulk_verify) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Meer dan één bevestigingsvakje.');
            }

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($selected_messages))
            {
                $errors[] = 'Selecteer ten minste één vraag of aanbod voor deze actie.';
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
                throw new BadRequestHttpException('Ongeldig formulier. Actie nazichtvakje klopt niet.');
            }

            if (isset($bulk_field_action)
                && $bulk_field_action !== $bulk_submit_action)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Actie waardeveld klopt niet.');
            }

            if (!isset($bulk_field_action))
            {
                throw new BadRequestHttpException('Ongeldig formulier. Waarde veld ontbreekt.');
            }

            $bulk_field_value = $bulk_field[$bulk_field_action];

            if (!isset($bulk_field_value) || !$bulk_field_value)
            {
                $errors[] = 'Bulk actie waarde-veld niet ingevuld.';
            }

            $update_msgs_ary  = [];

            $rows = $app['db']->executeQuery('select id_user, id, validity,
                id_category, msg_type
                from ' . $app['pp_schema'] . '.messages
                where id in (?)',
                [array_keys($selected_messages)],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

            foreach ($rows as $row)
            {
                if (!$app['pp_admin']
                    && ($row['id_user'] !== $app['s_id']))
                {
                    throw new AccessDeniedHttpException('Je bent niet de eigenaar van vraag of aanbod ' .
                        $row['content'] . ' ( ' . $row['id'] . ')');
                }

                $update_msgs_ary[$row['id']] = $row;
            }

            if ($bulk_submit_action === 'extend' && !count($errors))
            {
                foreach ($update_msgs_ary as $id => $row)
                {
                    $validity = $row['validity'];
                    $validity = gmdate('Y-m-d H:i:s', strtotime($validity . ' UTC') + (86400 * (int) $bulk_field_value));

                    $msg_update = [
                        'validity'		=> $validity,
                        'mdate'			=> gmdate('Y-m-d H:i:s'),
                        'exp_user_warn'	=> 'f',
                    ];

                    $app['db']->update($app['pp_schema'] . '.messages',
                        $msg_update, ['id' => $id]);
                }

                if (count($update_msgs_ary) > 1)
                {
                    $app['alert']->success('De berichten zijn verlengd.');
                }
                else
                {
                    $app['alert']->success('Het bericht is verlengd.');
                }

                $app['link']->redirect($app['r_messages'], $app['pp_ary'], []);
            }

            if ($bulk_submit_action === 'access' && !count($errors))
            {
                $msg_update = [
                    'local' => cnst_access::TO_LOCAL[$bulk_field_value],
                    'mdate' => gmdate('Y-m-d H:i:s'),
                ];

                foreach ($update_msgs_ary as $id => $row)
                {
                    $app['db']->update($app['pp_schema'] . '.messages', $msg_update, ['id' => $id]);
                }

                if (count($selected_messages) > 1)
                {
                    $app['alert']->success('De zichtbaarheid van de berichten is aangepast.');
                }
                else
                {
                    $app['alert']->success('De zichtbaarheid van het bericht is aangepast.');
                }

                $app['link']->redirect($app['r_messages'], $app['pp_ary'], []);
            }

            if ($bulk_submit_action === 'category' && !count($errors))
            {
                $to_id_category = (int) $bulk_field_value;

                $test_id_category = $app['db']->fetchColumn('select id
                    from ' . $app['pp_schema'] . '.categories
                    where id_parent <> 0
                        and leafnote = 1
                        and id = ?', [$to_id_category]);

                if (!$test_id_category)
                {
                    throw new BadRequestHttpException('Ongeldig categorie id ' . $to_id_category);
                }

                $msg_update = [
                    'id_category'   => $to_id_category,
                    'mdate'         => gmdate('Y-m-d H:i:s'),
                ];

                $stats_update = [
                    $to_id_category => [
                        'offer' => 0,
                        'want'  => 0,
                    ],
                ];

                foreach ($update_msgs_ary as $id => $row)
                {
                    $app['db']->update($app['pp_schema'] . '.messages', $msg_update, ['id' => $id]);

                    $type = cnst_message_type::FROM_DB[$row['msg_type']];
                    $id_category = $row['id_category'];

                    if ($id_category === $to_id_category)
                    {
                        continue;
                    }

                    if (!isset($stats_update[$id_category]))
                    {
                        $stats_update[$id_category] = [];
                    }

                    if (!isset($stats_update[$id_category][$type]))
                    {
                        $stats_update[$id_category][$type] = 0;
                    }

                    $stats_update[$id_category][$type]--;
                    $stats_update[$to_id_category][$type]++;
                }

                foreach ($stats_update as $id_category => $type_count_ary)
                {
                    foreach ($type_count_ary as $type => $count)
                    {
                        messages_edit::adjust_category_stats($type,
                            (int) $id_category, $count, $app['db'], $app['pp_schema']);
                    }
                }

                if (count($selected_messages) > 1)
                {
                    $app['alert']->success('De categorie van de berichten is aangepast.');
                }
                else
                {
                    $app['alert']->success('De categorie van het bericht is aangepast.');
                }

                $app['link']->redirect($app['r_messages'], $app['pp_ary'], []);
            }

            $app['alert']->error($errors);
        }

        $fetch_and_filter = messages_list::fetch_and_filter($request, $app);

        $messages = $fetch_and_filter['messages'];
        $params = $fetch_and_filter['params'];
        $categories = $fetch_and_filter['categories'];
        $categories_move_options = $fetch_and_filter['categories_move_options'];
        $cat_params = $fetch_and_filter['cat_params'];
        $s_owner = $fetch_and_filter['s_owner'];
        $out = $fetch_and_filter['out'];

        self::set_view_btn_nav($app['btn_nav'], $app['pp_ary'], $params, 'list');

        if ($app['pp_admin'])
        {
            $app['btn_top']->local('#bulk_actions', 'Bulk acties', 'envelope-o');
            $app['btn_nav']->csv();
        }

        $app['assets']->add(['table_sel.js']);

        $show_visibility_column = !$app['pp_guest'] && $app['intersystems']->get_count($app['pp_schema']);

        if (!count($messages))
        {
            return self::no_messages($app);
        }

        $out .= $app['pagination']->get();

        $out .= '<div class="panel panel-info printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped ';
        $out .= 'table-bordered table-hover footable csv" ';
        $out .= 'id="msgs" data-sort="false">';

        $out .= '<thead>';
        $out .= '<tr>';

        $th_params = $params;

        $table_header_ary = self::get_table_header_ary($params, $show_visibility_column);

        foreach ($table_header_ary as $key_orderby => $data)
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

            if ($app['pp_admin'] || $s_owner)
            {
                $out .= strtr(cnst_bulk::TPL_CHECKBOX_ITEM, [
                    '%id%'      => $msg['id'],
                    '%attr%'    => isset($selected_messages[$msg['id']]) ? ' checked' : '',
                    '%label%'   => ucfirst($msg['label']['type']),
                ]);
            }
            else
            {
                $out .= ucfirst($msg['label']['type']);
            }

            $out .= '</td>';

            $out .= '<td>';

            $out .= $app['link']->link_no_attr('messages_show', $app['pp_ary'],
                ['id' => $msg['id']], $msg['content']);

            $out .= '</td>';

            if (!isset($params['f']['uid']))
            {
                $out .= '<td>';
                $out .= $app['account']->link($msg['id_user'], $app['pp_ary']);
                $out .= '</td>';

                $out .= '<td>';
                $out .= $msg['postcode'] ?? '';
                $out .= '</td>';
            }

            if (!($params['f']['cid'] ?? false))
            {
                $out .= '<td>';
                $out .= $app['link']->link_no_attr($app['r_messages'], $app['pp_ary'],
                    $cat_params[$msg['id_category']],
                    $categories[$msg['id_category']]);
                $out .= '</td>';
            }

            $out .= '<td>';
            $out .= $app['date_format']->get($msg['validity'], 'day', $app['pp_schema']);
            $out .= '</td>';

            if ($show_visibility_column)
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

        $out .= $app['pagination']->get();

        if (($app['pp_admin'] || $s_owner) && count($messages))
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

            $out .= cnst_bulk::TPL_SELECT_BUTTONS;

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

            $out .= '<li><a href="#category_tab" ';
            $out .= 'data-toggle="tab">Categorie</a></li>';

            $out .= '</ul>';

            $out .= '<div class="tab-content">';

            $out .= '<div role="tabpanel" class="tab-pane active" id="extend_tab">';
            $out .= '<h3>Vraag en aanbod verlengen</h3>';

            $out .= '<form method="post">';

            $out .= '<div class="form-group">';
            $out .= '<label for="buld_field[extend]" class="control-label">';
            $out .= 'Verlengen met</label>';
            $out .= '<select name="bulk_field[extend]" id="extend" class="form-control">';
            $out .= $app['select']->get_options($extend_options, '30');
            $out .= "</select>";
            $out .= '</div>';

            $out .= strtr(cnst_bulk::TPL_CHECKBOX, [
                '%name%'    => 'bulk_verify[extend]',
                '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                '%attr%'    => ' required',
            ]);

            $out .= '<input type="submit" value="Verlengen" ';
            $out .= 'name="bulk_submit[extend]" class="btn btn-primary btn-lg">';

            $out .= $app['form_token']->get_hidden_input();

            $out .= '</form>';

            $out .= '</div>';

            if ($app['intersystem_en'])
            {
                $out .= '<div role="tabpanel" class="tab-pane" id="access_tab">';
                $out .= '<h3>Zichtbaarheid instellen</h3>';
                $out .= '<form method="post">';

                $out .= $app['item_access']->get_radio_buttons('bulk_field[access]', '', '', true);

                $out .= strtr(cnst_bulk::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[access]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $out .= '<input type="submit" value="Aanpassen" ';
                $out .= 'name="bulk_submit[access]" class="btn btn-primary btn-lg">';
                $out .= $app['form_token']->get_hidden_input();
                $out .= '</form>';
                $out .= '</div>';
            }

            $out .= '<div role="tabpanel" class="tab-pane" id="category_tab">';
            $out .= '<h3>Verhuizen naar categorie</h3>';
            $out .= '<form method="post">';

            $out .= strtr(cnst_bulk::TPL_SELECT, [
                '%options%' => $app['select']->get_options($categories_move_options, ''),
                '%name%'    => 'bulk_field[category]',
                '%label%'   => 'Categorie',
                '%attr%'    => ' required',
                '%fa%'      => 'clone',
            ]);

            $out .= strtr(cnst_bulk::TPL_CHECKBOX, [
                '%name%'    => 'bulk_verify[category]',
                '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                '%attr%'    => ' required',
            ]);

            $out .= '<input type="submit" value="Categorie anpassen" ';
            $out .= 'name="bulk_submit[category]" class="btn btn-primary btn-lg">';
            $out .= $app['form_token']->get_hidden_input();
            $out .= '</form>';
            $out .= '</div>';

            $out .= '</div>';

            $out .= '<div class="clearfix"></div>';
            $out .= '</div>';

            $out .= '</div></div>';
        }

        $app['menu']->set('messages');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }

    static public function no_messages(app $app):Response
    {
        $out = $app['pagination']->get();

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body">';
        $out .= '<p>Er zijn geen resultaten.</p>';
        $out .= '</div></div>';

        $out .= $app['pagination']->get();

        $app['menu']->set('messages');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
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

    public static function set_view_btn_nav(btn_nav $btn_nav, array $pp_ary, array $params, string $view)
    {
        $btn_nav->view('messages_list', $pp_ary,
            $params, 'Lijst', 'align-justify', $view === 'list');

        $btn_nav->view('messages_extended', $pp_ary,
            $params, 'Lijst met omschrijvingen', 'th-list', $view === 'extended');
    }

    public static function get_table_header_ary(
        array $params,
        bool $show_visibility_column
    ):array
    {
        $asc_preset_ary = [
            'asc'	=> '0',
            'fa' 	=> 'sort',
        ];

        $table_header_ary = [
            'm.msg_type' => array_merge($asc_preset_ary, [
                'lbl' => 'V/A']),
            'm.content' => array_merge($asc_preset_ary, [
                'lbl' => 'Wat']),
        ];

        if (!isset($params['f']['uid']))
        {
            $table_header_ary += [
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

        if (!($params['f']['cid'] ?? false))
        {
            $table_header_ary += [
                'c.fullname' => array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Categorie',
                    'data_hide'	=> 'phone, tablet',
                ]),
            ];
        }

        $table_header_ary += [
            'm.validity' => array_merge($asc_preset_ary, [
                'lbl' 	=> 'Geldig tot',
                'data_hide'	=> 'phone, tablet',
            ]),
        ];

        if ($show_visibility_column)
        {
            $table_header_ary += [
                'm.local' => array_merge($asc_preset_ary, [
                    'lbl' 	=> 'Zichtbaarheid',
                    'data_hide'	=> 'phone, tablet',
                ]),
            ];
        }

        $table_header_ary[$params['s']['orderby']]['asc']
            = $params['s']['asc'] ? '0' : '1';
        $table_header_ary[$params['s']['orderby']]['fa']
            = $params['s']['asc'] ? 'sort-asc' : 'sort-desc';

        unset($table_header_ary['m.cdate']);

        return $table_header_ary;
    }

    public static function fetch_and_filter(Request $request, app $app):array
    {
        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $s_owner = !$app['pp_guest']
            && $app['s_system_self']
            && isset($filter['uid'])
            && $app['s_id'] === (int) $filter['uid']
            && $app['s_id'];

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
            $filter['fcode'] = $app['account']->str((int) $filter['uid'], $app['pp_schema']);
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
                from ' . $app['pp_schema'] . '.users
                where letscode = ?', [$fcode]);

            if ($fuid)
            {
                $where_sql[] = 'u.id = ?';
                $params_sql[] = $fuid;

                $fcode = $app['account']->str((int) $fuid, $app['pp_schema']);
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
                from ' . $app['pp_schema'] . '.categories
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

        if ($app['pp_guest'])
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

        $query = 'select m.*, u.postcode, c.fullname
            from ' . $app['pp_schema'] . '.messages m, ' .
                $app['pp_schema'] . '.users u, ' .
                $app['pp_schema'] . '.categories c
                where m.id_user = u.id
                    and m.id_category = c.id' . $where_sql . '
            order by ' . $params['s']['orderby'] . ' ';

        $row_count = $app['db']->fetchColumn('select count(m.*)
            from ' . $app['pp_schema'] . '.messages m, ' .
                $app['pp_schema'] . '.users u
            where m.id_user = u.id' . $where_sql, $params_sql);

        $query .= $params['s']['asc'] ? 'asc ' : 'desc ';
        $query .= ' limit ' . $params['p']['limit'];
        $query .= ' offset ' . $params['p']['start'];

        $messages = [];

        $st = $app['db']->executeQuery($query, $params_sql);

        while ($msg = $st->fetch())
        {
            $msg['type'] = cnst_message_type::FROM_DB[$msg['msg_type']];
            $msg['label'] = messages_show::get_label($msg['type']);

            $messages[] = $msg;
        }

        $app['pagination']->init($app['r_messages'], $app['pp_ary'],
            $row_count, $params);

        $categories_filter_options = ['' => '-- alle categorieën --'];
        $categories_move_options = ['' => ''];

        $categories = $cat_params  = [];

        $cat_params_sort = $params;

        if ($params['s']['orderby'] === 'c.fullname')
        {
            unset($cat_params_sort['s']);
        }

        if (isset($filter['uid']))
        {
            $st = $app['db']->executeQuery('select c.*
                from ' . $app['pp_schema'] . '.categories c, ' .
                    $app['pp_schema'] . '.messages m
                where m.id_category = c.id
                    and m.id_user = ?
                order by c.fullname', [$filter['uid']]);
        }
        else
        {
            $st = $app['db']->executeQuery('select *
                from ' . $app['pp_schema'] . '.categories
                order by fullname');
        }

        while ($row = $st->fetch())
        {
            $categories_filter_options[$row['id']] = $row['id_parent'] ? ' . . ' : '';
            $categories_filter_options[$row['id']] .= $row['name'];
            $count_msgs = $row['stat_msgs_offers'] + $row['stat_msgs_wanted'];

            if ($row['id_parent'] && $count_msgs)
            {
                $categories_filter_options[$row['id']] .= ' (' . $count_msgs . ')';
            }

            $categories[$row['id']] = $row['fullname'];

            $cat_params[$row['id']] = $cat_params_sort;
            $cat_params[$row['id']]['f']['cid'] = $row['id'];

            if ($row['id_parent'])
            {
                $categories_move_options[$row['id']] = $row['fullname'];
            }
        }

        if ($app['pp_admin'] || $app['pp_user'])
        {
            if ($s_owner || !isset($filter['uid']))
            {
                $app['btn_top']->add('messages_add', $app['pp_ary'],
                    [], 'Vraag of aanbod toevoegen');
            }

            if (isset($filter['uid']))
            {
                if ($app['pp_admin'] && !$s_owner)
                {
                    $str = 'Vraag of aanbod voor ';
                    $str .= $app['account']->str((int) $filter['uid'], $app['pp_schema']);

                    $app['btn_top']->add('messages_add', $app['pp_ary'],
                        ['uid' => $filter['uid']], $str);
                }
            }
        }

        $app['assets']->add(['messages_filter.js']);

        $filter_panel_open = (($filter['fcode'] ?? false) && !isset($filter['uid']))
            || $filter_type
            || $filter_valid
            || $filter_ustatus;

        $filtered = ($filter['q'] ?? false) || $filter_panel_open;

        if (isset($filter['uid']))
        {
            if ($s_owner)
            {
                $app['heading']->add('Mijn vraag en aanbod');
            }
            else
            {
                $app['heading']->add_raw($app['link']->link_no_attr($app['r_messages'], $app['pp_ary'],
                    ['f' => ['uid' => $filter['uid']]],
                    'Vraag en aanbod'));

                $app['heading']->add(' van ');
                $app['heading']->add_raw($app['account']->link((int) $filter['uid'], $app['pp_ary']));
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

        $out .= $app['select']->get_options($categories_filter_options, (string) $filter['cid'] ?? '');

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
                'newuserdays'	=> $app['config']->get('newuserdays', $app['pp_schema']),
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

        $params_form = array_merge($params, $app['pp_ary']);
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

        return [
            'messages'                  => $messages,
            'params'                    => $params,
            'categories'                => $categories,
            'cat_params'                => $cat_params,
            'categories_move_options'   => $categories_move_options,
            's_owner'                   => $s_owner,
            'out'                       => $out,
        ];
    }
}
