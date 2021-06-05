<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\LinkRender;
use App\Render\PaginationRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\IntersystemsService;
use App\Service\LogDbService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Routing\Annotation\Route;

class LogsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/logs',
        name: 'logs',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'logs',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        PaginationRender $pagination_render,
        MenuService $menu_service,
        LinkRender $link_render,
        LogDbService $log_db_service,
        BtnNavRender $btn_nav_render,
        AssetsService $assets_service,
        IntersystemsService $intersystems_service,
        TypeaheadService $typeahead_service,
        AccountRender $account_render,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su,
        DateFormatService $date_format_service
    ):Response
    {
        $new_users_days = $config_service->get_int('users.new.days', $pp->schema());
        $new_users_enabled = $config_service->get_bool('users.new.enabled', $pp->schema());
        $leaving_users_enabled = $config_service->get_bool('users.leaving.enabled', $pp->schema());

        $su_intersystem_ary = $intersystems_service->get_eland($su->schema());

        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $log_db_service->update();

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

        $sql_map = [
            'where'     => [],
            'where_or'  => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['schema'] = $sql_map;
        $sql['schema']['where'][] = 'schema = ?';
        $sql['schema']['params'][] = $pp->schema();
        $sql['schema']['types'][] = \PDO::PARAM_STR;

        if (isset($filter['code'])
            && $filter['code'])
        {
            [$filter_code] = explode(' ', $filter['code']);

            $filter_user_id = $db->fetchOne('select id
                from ' . $pp->schema() . '.users
                where code = ?',
                [$filter_code], [\PDO::PARAM_STR]);

            if ($filter_user_id)
            {
                $sql['code'] = $sql_map;
                $sql['code']['where'][] = 'user_id = ? and user_schema = ?';
                $sql['code']['params'][] = $filter_user_id;
                $sql['code']['params'][] = $pp->schema();
                $sql['code']['types'][] = \PDO::PARAM_INT;
                $sql['code']['types'][] = \PDO::PARAM_STR;
                $params['f']['code'] = $filter['code'];
            }
        }

        if (isset($filter['type'])
            && $filter['type'])
        {
            $sql['type'] = $sql_map;
            $sql['type']['where'][] = 'type ilike ?';
            $sql['type']['params'][] = strtolower($filter['type']);
            $sql['type']['types'][] = \PDO::PARAM_STR;
            $params['f']['type'] = $filter['type'];
        }

        if (isset($filter['q'])
            && $filter['q'])
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][] = 'event ilike ?';
            $sql['q']['params'][] = '%' . $filter['q'] . '%';
            $sql['q']['types'][] = \PDO::PARAM_STR;
            $params['f']['q'] = $filter['q'];
        }

        if (isset($filter['fdate'])
            && $filter['fdate'])
        {
            $sql['fdate'] = $sql_map;
            $fdate = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter['fdate'] . ' UTC'));

            $sql['fdate']['where'][] = 'ts >= ?';
            $sql['fdate']['params'][] = $fdate;
            $sql['fdate']['types'][] = Types::DATETIME_IMMUTABLE;
            $params['f']['fdate'] = $filter['fdate'];
        }

        if (isset($filter['tdate'])
            && $filter['tdate'])
        {
            $sql['tdate'] = $sql_map;
            $tdate = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter['tdate'] . ' UTC'));

            $sql['tdate']['where'][] = 'ts <= ?';
            $sql['tdate']['params'][] = $tdate;
            $sql['tdate']['types'][] = Types::DATETIME_IMMUTABLE;
            $params['f']['tdate'] = $filter['tdate'];
        }

        $sql['pagination'] = $sql_map;
        $sql['pagination']['params'][] = $params['p']['limit'];
        $sql['pagination']['types'][] = \PDO::PARAM_INT;
        $sql['pagination']['params'][] = $params['p']['start'];
        $sql['pagination']['types'][] = \PDO::PARAM_INT;

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));

        $query = 'select *
            from xdb.logs
            where ' . $sql_where;
        $query .= ' order by ' . $params['s']['orderby'] . ' ';
        $query .= $params['s']['asc'] ? 'asc ' : 'desc ';
        $query .= 'limit ? offset ?';

        $rows = [];

        $stmt = $db->executeQuery($query,
            array_merge(...array_column($sql, 'params')),
            array_merge(...array_column($sql, 'types')));

        while ($row = $stmt->fetch())
        {
            $rows[] = $row;
        }

        $sql_omit_pagination = $sql;
        unset($sql_omit_pagination['pagination']);
        $sql_omit_pagination_where = implode(' and ', array_merge(...array_column($sql_omit_pagination, 'where')));

        $row_count = $db->fetchOne('select count(*)
            from xdb.logs
            where ' . $sql_omit_pagination_where,
            array_merge(...array_column($sql_omit_pagination, 'params')),
            array_merge(...array_column($sql_omit_pagination, 'types')));

        $pagination_render->init('logs', $pp->ary(),
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

        $btn_nav_render->csv();

        $assets_service->add(['datepicker']);

        $filtered = (isset($filter['q']) && $filter['q'] !== '')
            || (isset($filter['type']) && $filter['type'] !== '')
            || (isset($filter['code']) && $filter['code'] !== '')
            || (isset($filter['fdate']) && $filter['fdate'] !== '')
            || (isset($filter['tdate']) && $filter['tdate'] !== '');

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

        $out .= $typeahead_service->ini($pp->ary())
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
        $out .= $typeahead_service->ini($pp->ary())
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->add('accounts', ['status' => 'extern'])
            ->str([
                'filter'        => 'accounts',
                'new_users_days'        => $new_users_days,
                'new_users_enabled'     => $new_users_enabled,
                'leaving_users_enabled' => $leaving_users_enabled,
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
        $params_form['u'] = $su->id();

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

                $out .= $link_render->link_fa('logs', $pp->ary(), $th_params,
                    $data['lbl'], [], 'sort' . $data['indicator']);
            }

            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach($rows as $row)
        {
            $td = [];

            $td[] = $date_format_service->get($row['ts'], 'sec', $pp->schema());
            $td[] = $row['type'];
            $td[] .= $row['ip'];

            if ($row['is_master'])
            {
                $td[] = '<i> ** master ** </i>';
            }
            else
            {
                if (isset($row['user_schema'])
                    && isset($row['user_id'])
                    && ctype_digit((string) $row['user_id'])
                    && !empty($row['user_schema']))
                {
                    if ($row['user_schema'] === $pp->schema())
                    {
                        $td[] = $account_render->link($row['user_id'], $pp->ary());
                    }
                    else if (isset($su_intersystem_ary[$row['user_schema']]))
                    {
                        $td[] = $account_render->inter_link($row['user_id'], $row['user_schema'], $su);
                    }
                    else
                    {
                        $td[] = $account_render->str($row['user_id'], $row['user_schema']);
                    }
                }
                else
                {
                    $td[] = '<i> ** geen ** </i>';
                }
            }

            $td[] = $row['event'];

            $out .= '<tr><td>';
            $out .= implode('</td><td>', $td);
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div></div>';

        $out .= $pagination_render->get();

        $menu_service->set('logs');

        return $this->render('logs/logs.html.twig', [
            'content'   => $out,
            'filtered'  => $filtered,
            'schema'    => $pp->schema(),
        ]);
    }
}
