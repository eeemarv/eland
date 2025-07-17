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

    $context = [
      'token' => $message->token,
    ];

    $m_dispatch = new EmailDispatchMessage(
      template: 'password_reset/password_reset_confirm',
      context: $context,
      to: New AddressAry([$message->to]),
      schema: $schema,
    );

    $this->bus->dispatch($m_dispatch);
  }
}