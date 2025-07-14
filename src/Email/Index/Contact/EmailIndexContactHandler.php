<?php declare(strict_types=1);

namespace App\Email\Index\Contact;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class EmailIndexContactHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    #[Autowire('%env(MAIL_HOSTER_ADDRESS)%')]
    private readonly string $env_mail_hoster_address,
  ) {}

  public function __invoke(EmailIndexContactMessage $message):void
  {
    $context = [
      'message' => $message->message,
      'sender'  => [
        'email' => $message->reply_to->toString(),
        'agent' => $message->agent,
        'ip' => $message->ip,
      ],
    ];

    $hoster_address = new Address($this->env_mail_hoster_address);

    $dispatch = new EmailDispatchMessage(
      template: 'index/index_contact',
      context: $context,
      reply_to: $message->reply_to,
      to: New AddressAry([$hoster_address]),
    );

    $this->bus->dispatch($dispatch);
  }
}