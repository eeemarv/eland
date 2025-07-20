<?php declare(strict_types=1);

namespace App\Email\RegisterForm\Confirm;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailRegisterFormConfirmHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
  ) {}

  public function __invoke(EmailRegisterFormConfirmMessage $message):void
  {
    $schema = $message->schema;

    $confirm_data = [
      'email'       => $message->to->getAddress(),
      'first_name'  => $message->first_name,
      'last_name'   => $message->last_name,
      'postcode'    => $message->postcode,
      'tel'         => $message->tel,
      'gsm'         => $message->gsm,
    ];

    $dispatch = new EmailDispatchMessage(
      template: 'register_form/register_form_confirm',
      message_class: get_class($message),
      to: New AddressAry([$message->to]),
      add_confirm_token: true,
      confirm_data: $confirm_data,
      schema: $schema,
    );

    $this->bus->dispatch($dispatch);
  }
}