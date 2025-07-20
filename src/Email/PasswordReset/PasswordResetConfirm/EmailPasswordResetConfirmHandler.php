<?php declare(strict_types=1);

namespace App\Email\PasswordReset\PasswordResetConfirm;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailPasswordResetConfirmHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
  ) {}

  public function __invoke(EmailPasswordResetConfirmMessage $message):void
  {
    $schema = $message->schema;

    $confirm_data = [
      'email'   => $message->to->getAddress(),
      'user_id' => $message->user_id,
    ];

    $m_dispatch = new EmailDispatchMessage(
      template: 'password_reset/password_reset_confirm',
      message_class: get_class($message),
      to: New AddressAry([$message->to]),
      add_confirm_token: true,
      confirm_data: $confirm_data,
      schema: $schema,
    );

    $this->bus->dispatch($m_dispatch);
  }
}