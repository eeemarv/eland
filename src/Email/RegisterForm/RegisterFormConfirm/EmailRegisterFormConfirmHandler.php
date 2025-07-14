<?php declare(strict_types=1);

namespace App\Email\RegisterForm\RegisterFormConfirm;

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

    $context = [
      'token' => $message->token,
    ];

    $dispatch = new EmailDispatchMessage(
      template: 'register_form/register_form_confirm',
      context: $context,
      to: New AddressAry([$message->to]),
      schema: $schema,
    );

    $this->bus->dispatch($dispatch);
  }
}