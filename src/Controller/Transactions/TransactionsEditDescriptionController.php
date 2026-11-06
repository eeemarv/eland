<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsEditDescriptionCommand;
use App\Form\Type\Transactions\TransactionsEditDescriptionType;
use App\Repository\TransactionLogRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class TransactionsEditDescriptionController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/transactions/{id}/edit-description',
    name: 'transactions_edit_description',
    methods: ['GET', 'POST'],
    requirements: [
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.admin%',
    ],
    defaults: [
      'module' => 'transactions',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    TransactionRepository $transaction_repository,
    TransactionLogRepository $transaction_log_repository,
    PageParamsService $pp,
    SessionUserService $su,
    ConfigService $config_service,
    MessageBusInterface $bus,
  ): Response {
    if (!$config_service->get_bool(
        config_id: 'transactions.enabled',
        schema: $pp->schema_o(),
      )
    )
    {
      throw $this->createNotFoundException(
        'Transactions module not enabled.'
      );
    }

    $transaction = $transaction_repository->get_with_prev_next(
      id: $id,
      schema: $pp->schema_o(),
    );

    $description = $transaction['description'];
    $from_account_id = $transaction['id_from'];
    $to_account_id = $transaction['id_to'];
    $service_stuff = $transaction['service_stuff'];
    $amount = $transaction['amount'];

    $command = new TransactionsEditDescriptionCommand();
    $command->description = $description;
    $command->from_account_id = $from_account_id;
    $command->to_account_id = $to_account_id;
    $command->service_stuff = $service_stuff ?? 'null_service_stuff';
    $command->amount = $amount;

    $old_description = $description;

    $form = $this->createForm(
      type: TransactionsEditDescriptionType::class,
      data: $command,
      options: [
        'log_comment_enabled' => true,
      ],
    );
    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    )
    {
      $new_description = $command->description;

      if ($old_description === $new_description)
      {
         $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );

        $this->redirectToRoute(
          route: 'transactions_show', 
          parameters: [
            ...$pp->ary(),
            'id'  => $id,
          ],
        );
      }

      $log_comment = $form->get('log_comment')->getData();

      $transaction_repository->update_description(
        id: $id,
        description: $command->description,
        schema: $pp->schema_o(),
      );

      $transaction_log_repository->insert(
        transaction_id: $id,
        new_data: ['description' => $new_description],
        comment: $log_comment,
        created_by: $su->id() ?: null,
        route: $pp->route(),
        schema: $pp->schema_o(),
        old_data: ['description' => $old_description],
      );

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'transactions_edit_description.flash.success',
          'params' => [
            'old_description' => $old_description,
            'new_description' => $command->description,
          ],
        ],
      );

      return $this->redirectToRoute(
        route: 'transactions_show',
        parameters: [
          'id'  => $id,
          ...$pp->ary(),
        ],
      );
    }

    return $this->render('transactions/transactions_edit_description.html.twig', [
      'form' => $form->createView(),
      'id'  => $id,
      'transaction' => $transaction,
      'prev_id' => $transaction['prev_id'],
      'next_id' => $transaction['next_id'],
    ]);
  }
}
