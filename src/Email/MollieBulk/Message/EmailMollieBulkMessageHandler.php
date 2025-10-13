<?php declare(strict_types=1);

namespace App\Email\MollieBulk\Message;

use App\Cnst\BulkCnst;
use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use App\Repository\MollieRepository;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class EmailMollieBulkMessageHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly MollieRepository $mollie_repository,
    private readonly UserRepository $user_repository,
  ) {}

  public function __invoke(EmailMollieBulkMessageMessage $message):void
  {
    $sender_id = $message->sender_id;
    $payment_ids = $message->payment_ids;
    $schema = $message->schema;

    $reply_to = $this->user_repository->get_email_addresses(
      user_id: $sender_id,
      schema: $schema
    );

    $m_payments = $this->mollie_repository->get_payments_with_email_ary(
      payment_ids: $payment_ids,
      schema: $schema,
    );

    $bulk_id = Uuid::v4();

    foreach ($m_payments as $payment_id => $payment)
    {
      if (!count($payment['email_ary']))
      {
        continue;
      }

      $email_ary = [];

      foreach ($payment['email_ary'] as $email)
      {
        $email_ary[] = new Address($email, $payment['name']);
      }

      $checkout_token = Uuid::fromRfc4122($payment['checkout_token']);

      $context = [
        'subject'         => $message->subject,
        'checkout_token'  => $checkout_token->toBase58(),
        'amount'          => strtr($payment['amount'], '.', ',') . ' EUR',
        'description'     => $payment['code'] . ' ' . $payment['description'],
        'code'            => $payment['code'],
        'name'            => $payment['name'],
      ];

      $embedded_context = [];

      foreach (BulkCnst::MOLLIE_TPL_VARS as $tpl_key => $u_key)
      {
        $embedded_context[$tpl_key] = $context[$u_key];
      }

      $m_dispatch = new EmailDispatchMessage(
        template: 'mollie_bulk/mollie_bulk_message',
        message_class: get_class($message),
        context: $context,
        embedded_template: $message->message,
        embedded_context: $embedded_context,
        bulk_id: $bulk_id,
        bulk_created_by: $sender_id,
        mollie_payment_id: $payment_id,
        reply_to: $reply_to,
        to: new AddressAry($email_ary),
        schema: $schema
      );

      $this->bus->dispatch($m_dispatch);
    }
  }
}