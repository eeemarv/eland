<?php declare(strict_types=1);

namespace App\Email\Index\ContactSuccess;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailIndexContactSuccessHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
  ) {}

  public function __invoke(EmailIndexContactSuccessMessage $message):void
  {
    $context = [
      'message' => $message->message,
    ];

    $m_dispatch = new EmailDispatchMessage(
      template: 'index/index_contact_success',
      context: $context,
      to: New AddressAry([$message->to]),
    );

    $this->bus->dispatch($m_dispatch);
  }
}