<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Repository\TransactionRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class TransactionsShowController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/transactions/{id}',
    name: 'transactions_show',
    methods: ['GET'],
    priority: 10,
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'module'        => 'transactions',
    ],
  )]

  public function __invoke(
    int $id,
    TransactionRepository $transaction_repository,
    ConfigService $config_service,
    PageParamsService $pp,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'transactions.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Transactions module not enabled.');
    }

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

    return $this->render('transactions/transactions_show.html.twig', [
      'transaction'       => $transaction,
      'id'                => $id,
      'prev_id'           => $transaction['prev_id'],
      'next_id'           => $transaction['next_id'],
    ]);
  }
}
