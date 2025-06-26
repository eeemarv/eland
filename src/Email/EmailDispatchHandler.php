<?php declare(strict_types=1);

namespace App\Email;

use App\Email\EmailDispatchMessage;
use App\Service\ConfigService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Twig\Environment as Twig;

#[AsMessageHandler]
final class EmailDispatchHandler
{
  public function __construct(
    private readonly MailerInterface $mailer,
    private readonly Twig $twig,
    private readonly LoggerInterface $logger,
    private readonly ConfigService $config_service,
    #[Autowire('%env(MAIL_FROM_ADDRESS)%')]
    private readonly string $env_mail_from_address,
    #[Autowire('%env(MAIL_NOREPLY_ADDRESS)%')]
    private readonly string $env_mail_noreply_address
  ) {}

  public function __invoke(EmailDispatchMessage $message):void
  {
    $template_path = '@email/' . $message->template . '.html.twig';
    $context = $message->context;
    $log_context = [
      'template' => $message->template,
      'to' => $message->to->get_string(),
    ];

    if (isset($message->schema))
    {
      $schema = $message->schema->get();
      $context['schema'] = $schema;
      $log_context['schema'] = $schema;
    }

    $email = new TemplatedEmail();

    if (isset($message->reply_to))
    {
      $email->replyTo($message->reply_to);
      $log_context['reply_to'] = $message->reply_to->toString();
    }

    if (isset($message->cc))
    {
      $email->cc(...$message->cc->get());
      $log_context['cc'] = $message->cc->get_string();
    }

    if (isset($message->bcc))
    {
      $email->bcc(...$message->bcc->get());
      $log_context['bcc'] = $message->bcc->get_string();
    }

    if (isset($schema))
    {
      $email->getHeaders()->addHeader('X-Schema', $schema);
    }

    if (isset($message->from))
    {
      $from = $message->from;
    }
    else
    {
      $sender_name = 'eLAMD';

      if (isset($schema))
      {
        // TODO fetch email from system config
        $sender_name = $this->config_service->get_str('system.name', $schema);
      }

      $sender_email = isset($message->reply_to) ? $this->env_mail_from_address : $this->env_mail_noreply_address;
      $from = new Address($sender_email, $sender_name);
    }

    $log_context['from'] = $from->toString();

    $template = $this->twig->load($template_path);
    $subject = $template->renderBlock('subject', $message->context);
    $log_context['subject'] = $subject;

    if (isset($message->embedded_template))
		{
			try
			{
				$html_template = $this->twig->createTemplate($message->embedded_template);
				$context['html_content'] = $html_template->render($context);
			}
			catch (\Exception $e)
			{
				$this->logger->error('Mail Queue Process, embedded HTML template err: ' .
					$e->getMessage() . ' ::: ' .
					json_encode($context),
					$log_context);
				return;
			}
		}

    $email->from($from);
    $email->to(...$message->to->get());
    $email->subject($subject);
    $email->htmlTemplate($template_path);
    $email->context($context);

    $this->mailer->send($email);

    $log_context['template'] = $message->template;
    $log_context['to'] = $message->to->get_string();
    $log_context['from'] = $from->toString();
    $log_context['bcc'] = $message->bcc?->get_string();

    $this->logger->info('Email sent', $log_context);
  }
}