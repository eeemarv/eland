<?php declare(strict_types=1);

namespace App\Controller\Logs;

use App\Command\Logs\LogsFilterCommand;
use App\Form\Type\Logs\LogsFilterType;
use App\Repository\LogRepository;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class LogsController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/logs',
    name: 'logs',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'logs',
    ],
  )]

  public function __invoke(
    Request $request,
    LogRepository $log_repository,
    SystemsService $systems_service,
    PageParamsService $pp,
  ):Response
  {
    $inter_ary = $systems_service->get_inter_ary($pp->schema());  

    $filter_command = new LogsFilterCommand();
    $filter_form = $this->createForm(LogsFilterType::class, $filter_command);
    $filter_form->handleRequest($request);

    $pag = $request->query->all('p');
    $sort = $request->query->all('s');

    $pag_start = (int) ($pag['start'] ?? 0);
    $pag_limit = (int) ($pag['limit'] ?? 25);
    $sort_order_by = $sort['order_by'] ?? 'ts';
    $sort_asc = isset($sort['asc']) && $sort['asc'] ? true : false;

    $pag_params = [
      's'	=> [
        'order_by'	=> $sort_order_by,
        'asc'		=> $sort_asc,
      ],
      'p'	=> [
        'start'		=> $pag_start,
        'limit'		=> $pag_limit,
      ],
    ];

    $ret = $log_repository->get_filtered(
      user_id: $filter_command->user ?? null,
      type: $filter_command->type ?? null,
      event: $filter_command->q ?? null,
      from_date: null,
      to_date: null,
      start: $pag_start,
      limit: $pag_limit,
      order_by: $sort_order_by,
      asc: $sort_asc,
      inter_ary: $inter_ary,
      schema: $pp->schema_o(),
    );

    $row_count = $ret['row_count'];
    $logs = $ret['logs'];
    $remote_user_ary = $ret['remote_user_ary'];

    $filtered = isset($filter_command->q)
      || isset($filter_command->type)
      || isset($filter_command->user)
      || isset($filter_command->fdate)
      || isset($filter_command->tdate);

    return $this->render('logs/logs_list.html.twig', [
      'row_count'         => $row_count,
      'logs'              => $logs,
      'filter_form'       => $filter_form->createView(),
      'filtered'          => $filtered,
      'pag_params'        => $pag_params,
      'inter_ary'         => $inter_ary,
      'remote_user_ary'   => $remote_user_ary,
    ]);
  }
}
