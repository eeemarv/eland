<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsBulkServiceStuffCommand;
use App\Command\Transactions\TransactionsFilterCommand;
use App\Form\Type\Transactions\TransactionsBulkServiceStuffType;
use App\Form\Type\Transactions\TransactionsFilterType;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/transactions',
    name: 'transactions',
    methods: ['GET', 'POST'],
    priority: 10,
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'is_self'       => false,
      'module'        => 'transactions',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/transactions/self',
    name: 'transactions_self',
    methods: ['GET', 'POST'],
    priority: 20,
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'is_self'       => true,
      'module'        => 'transactions',
    ],
  )]

  public function __invoke(
    Request $request,
    bool $is_self,
    TransactionRepository $transaction_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'transactions.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Transactions module not enabled.');
    }

    if (!$request->isMethod('GET') && !$pp->is_admin())
    {
      throw new BadRequestHttpException('POST not allowed');
    }

    $service_stuff_enabled = $config_service->get_bool(
      config_id: 'transactions.fields.service_stuff.enabled',
      schema: $pp->schema_o(),
    );

    $filter_command = new TransactionsFilterCommand();

    if ($request->query->has('uid'))
    {
      $uid = (int) $request->query->get('uid');
    }

    if ($is_self)
    {
      $uid = $su->id();
    }

    if (isset($uid))
    {
      $filter_command->from_account = $uid;
      $filter_command->to_account = $uid;
      $filter_command->account_logic = 'or';
    }

    $filter_form = $this->createForm(
      type: TransactionsFilterType::class,
      data: $filter_command,
    );
    $filter_form->handleRequest($request);
    $filter_command = $filter_form->getData();

    $pag = $request->query->all('p');
    $sort = $request->query->all('s');

    $pag_start = (int) ($pag['start'] ?? 0);
    $pag_limit = (int) ($pag['limit'] ?? 25);
    $sort_order_by = $sort['order_by'] ?? 'created_at';
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

    $sel = $request->request->all('sel');

    $bulk_service_stuff_form = null;

    if ($pp->is_admin()
      && $service_stuff_enabled)
    {
      $bulk_service_stuff_command = new TransactionsBulkServiceStuffCommand();
      $bulk_service_stuff_form = $this->createForm(
        type: TransactionsBulkServiceStuffType::class,
        data: $bulk_service_stuff_command,
      );
      $bulk_service_stuff_form->handleRequest($request);
    }

    if (isset($bulk_service_stuff_form)
      && $bulk_service_stuff_form->isSubmitted()
      && $bulk_service_stuff_form->isValid()
    )
    {
      $bulk_service_stuff_command = $bulk_service_stuff_form->getData();
      $selected = $bulk_service_stuff_command->selected;
      $service_stuff = $bulk_service_stuff_command->service_stuff;
      $select_ary = explode(',', $selected);
      $s_transaction_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_transaction_ids[] = (int) trim($sel_id);
      }
      $transaction_repository->set_bulk_service_stuff(
        service_stuff: $service_stuff,
        transaction_ids: $s_transaction_ids,
        schema: $pp->schema_o(),
      );

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'transactions.bulk.service_stuff.flash.success',
          'params'  => [
            'count' => count($s_transaction_ids),
          ],
        ],
      );
      return $this->redirectToRoute(
        route: 'transactions',
        parameters: $pp->ary(),
      );
    }

    $fetched_data = $transaction_repository->get_filtered_transactions(
      filter_command: $filter_command,
      start: $pag_start,
      limit: $pag_limit,
      order_by: $sort_order_by,
      asc: $sort_asc,
      schema: $pp->schema_o(),
    );

    $filtered = !isset($uid) && (
      isset($filter_command->q)
      || isset($filter_command->from_account)
      || isset($filter_command->to_account)
      || isset($filter_command->from_date)
      || isset($filter_command->to_date)
      || (isset($filter_command->srvc)
        && $filter_command->srvc)
    );

    $filter_collapse = !$filtered;

    $transactions = $fetched_data['transactions'];
    $inter_ary = $fetched_data['inter_ary'];
    $count_ary = $fetched_data['count_ary'];

    return $this->render('transactions/transactions_list.html.twig', [
      'sel'                   => $sel,
      'filter_form'           => $filter_form->createView(),
      'filtered'              => $filtered,
      'filter_collapse'       => $filter_collapse,
      'row_count'             => $count_ary['row_count'],
      'is_self'               => $is_self,
      'uid'                   => $uid ?? null,
      'bulk_service_stuff_form' => $bulk_service_stuff_form ?? null,
      'bulk_actions_enabled' => isset($bulk_service_stuff_form),
      'pag_params'            => $pag_params,
      'transactions'          => $transactions,
      'inter_ary'             => $inter_ary,
      'count_ary'             => $count_ary,
    ]);
  }
}
