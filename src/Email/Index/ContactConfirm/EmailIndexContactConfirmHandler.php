<?php declare(strict_types=1);

namespace App\Email\Index\ContactConfirm;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailIndexContactConfirmHandler
{
    public function __construct(
      private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(EmailIndexContactConfirmMessage $message):void
    {
      $context = [
        'token' => $message->token,
      ];

      $dispatch = new EmailDispatchMessage(
        template: 'index/index_contact_confirm',
        context: $context,
        to: New AddressAry([$message->to]),
      );

      $this->bus->dispatch($dispatch);
    }
}