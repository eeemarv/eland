<?php declare(strict_types=1);

namespace App\Controller\Logs;

use App\Command\Logs\LogsFilterCommand;
use App\Form\Type\Logs\LogsFilterType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\DateFormatService;
use App\Service\IntersystemsService;
use App\Service\LogDbService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
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
        LinkRender $link_render,
        LogDbService $log_db_service,
        IntersystemsService $intersystems_service,
        AccountRender $account_render,
        PageParamsService $pp,
        SessionUserService $su,
        DateFormatService $date_format_service
    ):Response
    {
        $su_intersystem_ary = $intersystems_service->get_eland($su->schema());

        $filter_command = new LogsFilterCommand();
        $filter_form = $this->createForm(LogsFilterType::class, $filter_command);
        $filter_form->handleRequest($request);
        $filter_command = $filter_form->getData();

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

        if (isset($filter_command->user))
        {
            $sql['code'] = $sql_map;
            $sql['code']['where'][] = 'user_id = ? and user_schema = ?';
            $sql['code']['params'][] = $filter_command->user;
            $sql['code']['params'][] = $pp->schema();
            $sql['code']['types'][] = \PDO::PARAM_INT;
            $sql['code']['types'][] = \PDO::PARAM_STR;
            $params['f']['user'] = $filter_command->user;
        }

        if (isset($filter_command->type))
        {
            $sql['type'] = $sql_map;
            $sql['type']['where'][] = 'type ilike ?';
            $sql['type']['params'][] = strtolower($filter_command->type);
            $sql['type']['types'][] = \PDO::PARAM_STR;
            $params['f']['type'] = $filter_command->type;
        }

        if (isset($filter_command->q))
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][] = 'event ilike ?';
            $sql['q']['params'][] = '%' . $filter_command->q . '%';
            $sql['q']['types'][] = \PDO::PARAM_STR;
            $params['f']['q'] = $filter_command->q;
        }

        if (isset($filter_command->fdate))
        {
            $sql['fdate'] = $sql_map;
            $fdate = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->fdate . ' UTC'));

            $sql['fdate']['where'][] = 'ts >= ?';
            $sql['fdate']['params'][] = $fdate;
            $sql['fdate']['types'][] = Types::DATETIME_IMMUTABLE;
            $params['f']['fdate'] = $filter_command->fdate;
        }

        if (isset($filter_command->tdate))
        {
            $sql['tdate'] = $sql_map;
            $tdate = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->tdate . ' UTC'));

            $sql['tdate']['where'][] = 'ts <= ?';
            $sql['tdate']['params'][] = $tdate;
            $sql['tdate']['types'][] = Types::DATETIME_IMMUTABLE;
            $params['f']['tdate'] = $filter_command->tdate;
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

        $res = $db->executeQuery($query,
            array_merge(...array_column($sql, 'params')),
            array_merge(...array_column($sql, 'types')));

        while ($row = $res->fetchAssociative())
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

        $asc_preset_ary = [
            'asc'	=> 0,
            'indicator' => '',
        ];

        $tableheader_ary = [
            'ts' => [
                ...$asc_preset_ary,
                'lbl' => 'Tijd',
            ],
            'type' => [
                ...$asc_preset_ary,
                'lbl' => 'Type',
            ],
            'ip'	=> [
                ...$asc_preset_ary,
                'lbl' 		=> 'ip',
                'data_hide' => 'phone, tablet',
            ],
            'code'	=> [...$asc_preset_ary,
                'lbl' 		=> 'Gebruiker',
                'data_hide'	=> 'phone, tablet',
            ],
            'event'	=> [
                ...$asc_preset_ary,
                'lbl' 		=> 'Event',
                'data_hide'	=> 'phone',
            ],
        ];

        $tableheader_ary[$params['s']['orderby']]['asc'] = $params['s']['asc'] ? 0 : 1;
        $tableheader_ary[$params['s']['orderby']]['indicator'] = $params['s']['asc'] ? '-asc' : '-desc';

        $filtered = isset($filter_command->q)
            || isset($filter_command->type)
            || isset($filter_command->user)
            || isset($filter_command->fdate)
            || isset($filter_command->tdate);

        $out = '<div class="panel panel-default printview">';

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

        return $this->render('logs/logs.html.twig', [
            'data_list_raw'     => $out,
            'row_count'         => $row_count,
            'filter_form'       => $filter_form->createView(),
            'filtered'          => $filtered,
        ]);
    }
}
