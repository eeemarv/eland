<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class logs
{
    public function logs(Request $request, app $app):Response
    {
        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $app['log_db']->update();

        $params = [
            's'	=> [
                'orderby'	=> $sort['orderby'] ?? 'ts',
                'asc'		=> $sort['asc'] ?? 0,
            ],
            'p'	=> [
                'start'		=> $pag['start'] ?? 0,
                'limit'		=> $pag['limit'] ?? 25,
            ],
        ];

        $params_sql = $where_sql = [];

        $params_sql[] = $app['tschema'];

        if (isset($filter['code'])
            && $filter['code'])
        {
            [$l_code] = explode(' ', $filter['code']);

            $where_sql[] = 'letscode = ?';
            $params_sql[] = strtolower($l_code);
            $params['f']['code'] = $filter['code'];
        }

        if (isset($filter['type'])
            && $filter['type'])
        {
            $where_sql[] = 'type ilike ?';
            $params_sql[] = strtolower($filter['type']);
            $params['f']['type'] = $filter['type'];
        }

        if (isset($filter['q'])
            && $filter['q'])
        {
            $where_sql[] = 'event ilike ?';
            $params_sql[] = '%' . $filter['q'] . '%';
            $params['f']['q'] = $filter['q'];
        }

        if (isset($filter['fdate'])
            && $filter['fdate'])
        {
            $where_sql[] = 'ts >= ?';
            $params_sql[] = $filter['fdate'];
            $params['f']['fdate'] = $filter['fdate'];
        }

        if (isset($filter['tdate'])
            && $filter['tdate'])
        {
            $where_sql[] = 'ts <= ?';
            $params_sql[] = $filter['tdate'];
            $params['f']['tdate'] = $filter['tdate'];
        }

        if (count($where_sql))
        {
            $where_sql = ' and ' . implode(' and ', $where_sql) . ' ';
        }
        else
        {
            $where_sql = '';
        }

        $query = 'select *
            from xdb.logs
                where schema = ?' . $where_sql . '
            order by ' . $params['s']['orderby'] . ' ';

        $row_count = $app['db']->fetchColumn('select count(*)
            from xdb.logs
            where schema = ?' . $where_sql, $params_sql);

        $query .= $params['s']['asc'] ? 'asc ' : 'desc ';
        $query .= ' limit ' . $params['p']['limit'];
        $query .= ' offset ' . $params['p']['start'];

        $rows = $app['db']->fetchAll($query, $params_sql);

        $app['pagination']->init('logs', $app['pp_ary'],
            $row_count, $params);

        $asc_preset_ary = [
            'asc'	=> 0,
            'indicator' => '',
        ];

        $tableheader_ary = [
            'ts' => array_merge($asc_preset_ary, [
                'lbl' => 'Tijd']),
            'type' => array_merge($asc_preset_ary, [
                'lbl' => 'Type']),

            'ip'	=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'ip',
                'data_hide' => 'phone, tablet',
            ]),
            'code'	=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'Gebruiker',
                'data_hide'	=> 'phone, tablet',
            ]),
            'event'	=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'Event',
                'data_hide'	=> 'phone',
            ]),
        ];

        $tableheader_ary[$params['s']['orderby']]['asc'] = $params['s']['asc'] ? 0 : 1;
        $tableheader_ary[$params['s']['orderby']]['indicator'] = $params['s']['asc'] ? '-asc' : '-desc';

        $app['btn_nav']->csv();

        $app['assets']->add(['datepicker', 'csv.js']);

        $filtered = (isset($filter['q']) && $filter['q'] !== '')
            || (isset($filter['type']) && $filter['type'] !== '')
            || (isset($filter['code']) && $filter['code'] !== '')
            || (isset($filter['fdate']) && $filter['fdate'] !== '')
            || (isset($filter['tdate']) && $filter['tdate'] !== '');

        $app['heading']->add('Logs');
        $app['heading']->add_filtered($filtered);
        $app['heading']->fa('history');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get" class="form-horizontal">';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-4">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="q_addon">';
        $out .= '<i class="fa fa-search"></i></span>';

        $out .= '<input type="text" class="form-control" ';
        $out .= 'aria-describedby="q_addon" ';
        $out .= 'name="f[q]" id="q" placeholder="Zoek Event" ';
        $out .= 'value="';
        $out .= $filter['q'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-3">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="type_addon">';
        $out .= 'Type</span>';

        $out .= '<input type="text" class="form-control" ';
        $out .= 'aria-describedby="type_addon" ';
        $out .= 'data-typeahead="';

        $out .= $app['typeahead']->ini($app['pp_ary'])
            ->add('log_types', [])
            ->str();

        $out .= '" ';
        $out .= 'name="f[type]" id="type" placeholder="Type" ';
        $out .= 'value="';
        $out .= $filter['type'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-3">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="code_addon">';
        $out .= '<span class="fa fa-user"></span></span>';

        $out .= '<input type="text" class="form-control" ';
        $out .= 'aria-describedby="code_addon" ';

        $out .= 'data-typeahead="';
        $out .= $app['typeahead']->ini($app['pp_ary'])
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->add('accounts', ['status' => 'extern'])
            ->str([
                'filter'        => 'accounts',
                'newuserdays'   => $app['config']->get('newuserdays', $app['tschema']),
            ]);
        $out .= '" ';

        $out .= 'name="f[code]" id="code" placeholder="Account Code" ';
        $out .= 'value="';
        $out .= $filter['code'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-2">';
        $out .= '<input type="submit" value="Toon" ';
        $out .= 'class="btn btn-default btn-block" name="zend">';
        $out .= '</div>';

        $out .= '</div>';

        $params_form = $params;
        unset($params_form['f']);
        unset($params_form['uid']);
        unset($params_form['p']['start']);

        $params_form['r'] = 'admin';
        $params_form['u'] = $app['s_id'];

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

        $out .= '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-hover table-bordered table-striped footable csv" ';
        $out .= 'data-sort="false">';
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

                $out .= '<a href="';
                $out .= $app->path('logs', array_merge($app['pp_ary'], $th_params));
                $out .= '">';
                $out .= $data['lbl'];
                $out .= '&nbsp;';
                $out .= '<i class="fa fa-sort';
                $out .= $data['indicator'];
                $out .= '"></i>';
                $out .= '</a>';
            }
            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach($rows as $value)
        {
            $out .= '<tr>';
            $out .= '<td>';
            $out .= $app['date_format']->get($value['ts'], 'sec', $app['tschema']);
            $out .= '</td>';
            $out .= '<td>';
            $out .= $value['type'];
            $out .= '</td>';
            $out .= '<td>';
            $out .= $value['ip'];
            $out .= '</td>';
            $out .= '<td>';

            if (isset($value['user_schema'])
                && isset($value['user_id'])
                && ctype_digit((string) $value['user_id'])
                && !empty($value['user_schema']))
            {
                $params_context = [
                    'system'        => $app['systems']->get_system($value['user_schema']),
                    'role_short'    => $value['user_schema'] === $app['tschema'] ? 'a' : 'g',
                ];

                $out .= $app['account']->link($value['user_id'], $params_context);
            }
            else
            {
                $out .= '<i> ** geen ** </i>';
            }

            $out .= '</td>';
            $out .= '<td>' . $value['event'] . '</td>';
            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div></div>';

        $out .= $app['pagination']->get();

        $app['menu']->set('logs');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['tschema'],
        ]);
    }
}
