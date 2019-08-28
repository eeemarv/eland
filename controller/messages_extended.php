<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\messages_list;

class messages_extended
{
    public function messages_extended(Request $request, app $app):Response
    {
        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && isset($filter['uid'])
            && $app['s_id'] === (int) $filter['uid']
            && $app['s_id'];

        $view = 'extended';

        $v_list = $view === 'list';
        $v_extended = $view === 'extended';

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
            $filter['fcode'] = $app['account']->str((int) $filter['uid'], $app['tschema']);
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

                $fcode = $app['account']->str((int) $fuid, $app['tschema']);
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
            $row_count, $params);

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
            if ($s_owner || !isset($filter['uid']))
            {
                $app['btn_top']->add('messages_add', $app['pp_ary'],
                    [], 'Vraag of aanbod toevoegen');
            }

            if (isset($filter['uid']))
            {
                if ($app['s_admin'] && !$s_owner)
                {
                    $str = 'Vraag of aanbod voor ';
                    $str .= $app['account']->str((int) $filter['uid'], $app['tschema']);

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
            if ($s_owner)
            {
                $app['heading']->add('Mijn vraag en aanbod');
            }
            else
            {
                $app['heading']->add($app['link']->link_no_attr($app['r_messages'], $app['pp_ary'],
                    ['f' => ['uid' => $filter['uid']]],
                    'Vraag en aanbod'));

                $app['heading']->add(' van ');
                $app['heading']->add($app['account']->link((int) $filter['uid'], $app['pp_ary']));
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

        $out .= $app['select']->get_options($cats, (string) $filter['cid'] ?? '');

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

        $out .= messages_list::get_checkbox_filter($offerwant_options, 'type', $filter);

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

        $out .= messages_list::get_checkbox_filter($valid_options, 'valid', $filter);

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

        $out .= messages_list::get_checkbox_filter($user_status_options, 'ustatus', $filter);

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

        $out .= $app['pagination']->get();

        if (!count($messages))
        {
            return messages_list::no_messages($app['pagination'], $app['tpl'], $out);
        }

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
                    ['class'	=> 'btn btn-primary'],
                    'pencil');

                $out .= $app['link']->link_fa('messages_del', $app['pp_ary'],
                    ['id' => $msg['id']], 'Verwijderen',
                    ['class' => 'btn btn-danger'],
                    'times');

                $out .= '</span>';
            }
            $out .= '</p>';
            $out .= '</div>';

            $out .= '</div>';
        }

        $out .= $app['pagination']->get();

        $app['tpl']->add($out);
        $app['tpl']->menu('messages');

        return $app['tpl']->get();
    }
}
