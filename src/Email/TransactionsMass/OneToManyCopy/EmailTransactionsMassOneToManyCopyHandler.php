<?php declare(strict_types=1);

namespace App\Email\TransactionsMass\OneToManyCopy;

use App\Email\EmailDispatchMessage;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailTransactionsMassOneToManyCopyHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly TransactionRepository $transaction_repository,
    private readonly UserRepository $user_repository,
  ) {}

  public function __invoke(EmailTransactionsMassOneToManyCopyMessage $message):void
  {
    $bulk_id = $message->bulk_id;
    $schema = $message->schema;

    $transactions = $this->transaction_repository->get_all_by_bulk_id(
      bulk_id: $bulk_id,
      schema: $schema,
    );

    if (!count($transactions))
    {
      return;
    }

    $first_transaction = $transactions[array_key_first($transactions)];

    $context = [
      'transactions' => $transactions,
      'count' => count($transactions),
      'total_amount' => array_sum(array_column($transactions, 'amount')),
      'description' => $first_transaction['description'],
      'from_id' => $first_transaction['from_id'],
      'from_code' => $first_transaction['from_code'],
      'from_name' => $first_transaction['from_name'],
      'created_by' => $first_transaction['created_by'],
    ];

    $to = $this->user_repository->get_email_addresses(
      user_id: $first_transaction['created_by'],
      schema: $schema
    );

    $m_dispatch = new EmailDispatchMessage(
      template: 'transactions_mass/transactions_mass_one_to_many_copy',
      message_class: get_class($message),
      context: $context,
      to: $to,
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}