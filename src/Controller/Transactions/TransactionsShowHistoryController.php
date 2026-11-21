<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Repository\TransactionLogRepository;
use App\Repository\TransactionRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class TransactionsShowHistoryController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Transactions,
    path: '/transactions/{id}/history',
    name: 'transactions_show_history',
    methods: ['GET'],
  )]

  public function __invoke(
    int $id,
    TransactionLogRepository $transaction_log_repository,
    TransactionRepository $transaction_repository,
    PageParamsService $pp,
  ):Response
  {

    $transaction = $transaction_repository->get_with_prev_next(
      id: $id,
      schema: $pp->schema_o(),
    );

		if ($transaction === false)
		{
			throw $this->createNotFoundException(
        'Transaction ' . $id . ' not found'
      );
		}

    $history_ary = $transaction_log_repository->get_history(
      transaction_id: $id,
      schema: $pp->schema_o(),
    );

    return $this->render('transactions/transactions_show_history.html.twig', [
      'transaction'       => $transaction,
      'id'                => $id,
      'prev_id'           => $transaction['prev_id'],
      'next_id'           => $transaction['next_id'],
      'history_ary'       => $history_ary,
    ]);
  }
}
