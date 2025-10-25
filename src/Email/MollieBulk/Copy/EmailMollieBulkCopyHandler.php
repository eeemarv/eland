<?php declare(strict_types=1);

namespace App\Email\MollieBulk\Copy;

use App\Email\EmailDispatchMessage;
use App\Repository\MollieRepository;
use App\Repository\UserRepository;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class EmailMollieBulkCopyHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly UserRepository $user_repository,
    private readonly MollieRepository $mollie_repository,
    private readonly HtmlSanitizerInterface $html_sanitizer,
  ) {}

  public function __invoke(EmailMollieBulkCopyMessage $message):void
  {
    $schema = $message->schema;
    $sanitized_content = $this->html_sanitizer->sanitize($message->content);

    $context = [
      'to_user_id'  => $message->to_user_id,
      'html_content'  => $sanitized_content,
      'subject'   => $message->subject,
      'dummy_checkout_token' => Uuid::v4()->toBase58(),
    ];

    $sent_ary = $this->mollie_repository->get_payments_basic_info(
      payment_ids: $message->payment_ids_sent,
      schema: $schema,
    );

    $context['sent_ary'] = array_map(function($e){
      $e['amount'] = strtr($e['amount'], '.', ',') . ' EUR';
      return $e;
    }, $sent_ary);

    $not_sent_ary = $this->mollie_repository->get_payments_basic_info(
      payment_ids: $message->payment_ids_not_sent,
      schema: $schema,
    );

    $context['not_sent_ary'] = array_map(function($e){
      $e['amount'] = strtr($e['amount'], '.', ',') . ' EUR';
      return $e;
    }, $not_sent_ary);

    $to = $this->user_repository->get_email_addresses(
      user_id: $message->to_user_id,
      schema: $schema
    );

    $m_dispatch = new EmailDispatchMessage(
      template: 'mollie_bulk/mollie_bulk_copy',
      message_class: get_class($message),
      context: $context,
      to: $to,
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}