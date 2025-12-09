<?php declare(strict_types=1);

namespace App\Email\RegisterForm\Admin;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use App\Service\ConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class EmailRegisterFormAdminHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly ConfigService $config_service,
  ) {}

  public function __invoke(EmailRegisterFormAdminMessage $message):void
  {
    $schema = $message->schema;

    $context = [
      'user_id' => $message->user_id,
    ];

    $to_email_ary = $this->config_service->get_ary(
      config_id: 'mail.addresses.support',
      schema: $schema,
    );
    $to = array_map(fn($e) => new Address($e), $to_email_ary);

    $m_dispatch = new EmailDispatchMessage(
      template: 'register_form/register_form_admin',
      message_class: get_class($message),
      context: $context,
      to: New AddressAry($to),
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}