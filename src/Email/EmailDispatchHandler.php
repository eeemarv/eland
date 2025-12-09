<?php declare(strict_types=1);

namespace App\Email;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use App\Repository\EmailSentRepository;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;
use Twig\Environment as Twig;

#[AsMessageHandler]
final class EmailDispatchHandler
{
  public function __construct(
    private readonly MailerInterface $mailer,
    private readonly Twig $twig,
    private readonly LoggerInterface $logger,
    private readonly EmailSentRepository $email_sent_repository,
    private readonly ConfigService $config_service,
    private readonly UserRepository $user_repository,
    #[Autowire('%env(MAIL_FROM_ADDRESS)%')]
    private readonly string $env_mail_from_address,
    #[Autowire('%env(MAIL_NOREPLY_ADDRESS)%')]
    private readonly string $env_mail_noreply_address,
    #[Target(name: 'no_img_email_sanitizer')]
    private readonly HtmlSanitizerInterface $html_sanitizer,
  ) {}

  public function __invoke(EmailDispatchMessage $message):void
  {
    $template_path = '@email/' . $message->template . '.html.twig';
    $context = $message->context;

    $log_context = [
      'template' => $message->template,
      'to' => $message->to->str(),
    ];

    if (isset($message->schema))
    {
      $schema = $message->schema->str();
      $context['schema'] = $schema;
      $log_context['schema'] = $schema;
    }

    $email = new TemplatedEmail();

    if (isset($message->reply_to))
    {
      $email->replyTo(...$message->reply_to->ary());
      $log_context['reply_to'] = $message->reply_to->str();
    }

    if (isset($message->cc))
    {
      $email->cc(...$message->cc->ary());
      $log_context['cc'] = $message->cc->str();
    }

    if (isset($message->bcc))
    {
      $email->bcc(...$message->bcc->ary());
      $log_context['bcc'] = $message->bcc->str();
    }

    if (isset($schema))
    {
      $email->getHeaders()->addHeader('X-Eland-Schema', $schema);
    }

    if (isset($message->from))
    {
      $from = $message->from;
    }
    else
    {
      $sender_name = 'eLAND';

      if (isset($schema))
      {
        // TODO fetch email from system config
        $sender_name = $this->config_service->get_str(
          config_id: 'system.name',
          schema: $message->schema,
        );
      }

      $sender_email = isset($message->reply_to) ? $this->env_mail_from_address : $this->env_mail_noreply_address;
      $from = new Address($sender_email, $sender_name);
    }

    $log_context['from'] = $from->toString();

    $template = $this->twig->load($template_path);
    $subject = $template->renderBlock('subject_render', $context);
    $log_context['subject'] = $subject;

    if (isset($message->embedded_template))
		{
      $embedded_context = $message->embedded_context;
      $embedded_template = $this->html_sanitizer->sanitize($message->embedded_template);

			try
			{
				$html_template = $this->twig->createTemplate($embedded_template);
				$context['html_content'] = $html_template->render($embedded_context);
			}
			catch (\Exception $e)
			{
				$this->logger->error('Mail Queue Process, embedded HTML template err: ' .
					$e->getMessage() . ' ::: ' .
					json_encode($embedded_context) .
					json_encode($context),
					$log_context);
				return;
			}

      if (isset($message->bulk_id)
        && isset($message->bulk_created_by)
        && isset($message->schema)
      )
      {
        $this->email_sent_repository->insert_bulk_if_not_exists(
          content: $embedded_template,
          subject: $subject,
          bulk_id: $message->bulk_id,
          created_by: $message->bulk_created_by,
          schema: $message->schema,
        );
      }
		}

    $confirm_token = null;

    if ($message->add_confirm_token)
    {
      $confirm_token = Uuid::v4();
      $confirm_token_base58 = $confirm_token->toBase58();
      $context['confirm_token'] = $confirm_token_base58;
      $log_context['confirm_token'] = $confirm_token_base58;
      $log_context['confirm_token_rfc4122'] = $confirm_token->toRfc4122();
    }

    $email_token = Uuid::v4();
    $email_token_base58 = $email_token->toBase58();
    $context['email_token'] = $email_token_base58;
    $log_context['email_token'] = $email_token_base58;
    $log_context['email_token_rfc4122'] = $email_token->toRfc4122();

    $email->getHeaders()->addHeader('X-Eland-Token', $email_token_base58);

    $email->from($from);
    $email->to(...$message->to->ary());
    $email->subject($subject);
    $email->htmlTemplate($template_path);
    $email->context($context);

    $this->email_sent_repository->register(
      email_token: $email_token,
      to_addresses: $message->to,
      from_address: $from,
      bcc_addresses: $message->bcc ?? new AddressAry([]),
      cc_addresses: $message->cc ?? new AddressAry([]),
      reply_to_addresses: $message->reply_to ?? new AddressAry([]),
      confirm_token: $confirm_token,
      confirm_data: $message->confirm_data,
      template: $message->template,
      message_class: $message->message_class,
      subject: $subject,
      bulk_id: $message->bulk_id,
      mollie_payment_id: $message->mollie_payment_id,
      schema: $message->schema
    );

    $this->mailer->send($email);

    $log_context['template'] = $message->template;
    $log_context['to'] = $message->to->str();
    $log_context['from'] = $from->toString();

    $this->logger->info('Email sent', $log_context);
  }
}