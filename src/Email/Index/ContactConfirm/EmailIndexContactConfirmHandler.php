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
    $confirm_data = [
      'message' => $message->message,
      'email'   => $message->to->getAddress(),
      'ip'      => $message->ip,
      'agent'   => $message->agent
    ];

    $m_dispatch = new EmailDispatchMessage(
      template: 'index/index_contact_confirm',
      to: New AddressAry([$message->to]),
      add_confirm_token: true,
      confirm_data: $confirm_data
    );

    $this->bus->dispatch($m_dispatch);
  }
}