<?php declare(strict_types=1);

namespace App\Email\SupportForm\Admin;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class EmailSupportFormAdminHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly UserRepository $user_repository,
    private readonly ConfigService $config_service,
  ) {}

  public function __invoke(EmailSupportFormAdminMessage $message):void
  {
    $schema = $message->schema;
    $user_id = $message->user_id;

    $to_email_ary = $this->config_service->get_ary('mail.addresses.support', $schema->str());
    $to = array_map(fn($e) => new Address($e), $to_email_ary);

    $reply_to = $this->user_repository->get_email_addresses(
      user_id: $user_id,
      schema: $schema
    );

    $context = [
      'message'   => $message->message,
      'user_id'   => $user_id,
      'can_reply' => $reply_to->count() > 0,
    ];

    $m_dispatch = new EmailDispatchMessage(
      template: 'support_form/support_form_admin',
      message_class: get_class($message),
      context: $context,
      reply_to: $reply_to,
      to: New AddressAry($to),
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}