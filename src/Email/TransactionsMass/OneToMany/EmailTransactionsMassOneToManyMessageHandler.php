<?php declare(strict_types=1);

namespace App\Email\TransactionsMass\OneToMany;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use App\Repository\TransactionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailTransactionsMassOneToManyMessageHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly TransactionRepository $transaction_repository,
  ) {}

  public function __invoke(EmailTransactionsMassOneToManyMessage $message):void
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

    shuffle($transactions);

    foreach ($transactions as $transaction)
    {
      if (!count($transaction['from_email']))
      {
        continue;
      }

      $to = new AddressAry($transaction['from_email']);

      $context = [
        ...$transaction,
        'account_info' => [
          'id'    => $transaction['to_id'],
          'code'  => $transaction['to_code'],
          'name'  => $transaction['to_name'],
          'balance' => $transaction['to_balance'],
        ]
      ];

      $m_dispatch = new EmailDispatchMessage(
        template: 'transactions_mass/transactions_mass_one_to_many',
        message_class: get_class($message),
        context: $context,
        bulk_id: $bulk_id,
        bulk_created_by: $transaction['created_by'],
        to: $to,
        schema: $schema
      );

      $this->bus->dispatch($m_dispatch);
    }
  }
}