<?php declare(strict_types=1);

namespace App\Email\Mollie\Confirmation;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use App\Repository\MollieRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class EmailMollieConfirmationHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly MollieRepository $mollie_repository,
  ) {}

  public function __invoke(EmailMollieConfirmationMessage $message):void
  {
    $payment_id = $message->payment_id;
    $schema = $message->schema;

    $payment = $this->mollie_repository->get_payment_with_email_addresses(
      payment_id: $payment_id,
      schema: $schema,
    );

    if ($payment === false)
    {
      error_log('payment with id ' . $payment_id . ' not found');
      return;
    }

    if (!$payment['is_paid'])
    {
      error_log('payment with id ' . $payment_id . ' is not paid');
      return;
    }

    if (!count($payment['email_addresses']))
    {
      error_log('No email address set for ' . json_encode($payment));
      return;
    }

    $to_ary = [];

    foreach($payment['email_addresses'] as $email)
    {
      $to_ary[] = new Address($email, $payment['name']);
    }

    $context = [
      'amount'          => strtr($payment['amount'], '.', ',') . ' EUR',
      'description'     => $payment['code'] . ' ' . $payment['description'],
      'code'            => $payment['code'],
      'name'            => $payment['name'],
    ];

    $m_dispatch = new EmailDispatchMessage(
      template: 'mollie/mollie_confirmation',
      message_class: get_class($message),
      context: $context,
      mollie_payment_id: $payment_id,
      to: new AddressAry($to_ary),
      schema: $schema,
    );

    $this->bus->dispatch($m_dispatch);
  }
}