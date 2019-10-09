<?php declare(strict_types=1);

namespace App\Queue;

use App\Queue\QueueInterface;
use League\HTMLToMarkdown\HtmlConverter;
use App\Service\Queue;
use Psr\Log\LoggerInterface;
use Twig_Environment as Twig;
use App\Service\ConfigService;
use App\Service\MailAddrSystemService;
use App\Service\EmailValidate;
use App\Service\SystemsService;

class MailQueue implements QueueInterface
{
	protected $converter;
	protected $mailer;
	protected $queue;
	protected $logger;
	protected $twig;
	protected $config_service;
	protected $mail_addr_system_service;
	protected $email_validate;
	protected $systems_service;

	public function __construct(
		Queue $queue,
		LoggerInterface $logger,
		Twig $twig,
		ConfigService $config_service,
		MailAddrSystemService $mail_addr_system_service,
		EmailValidate $email_validate,
		SystemsService $systems_service
	)
	{
		$this->queue = $queue;
		$this->logger = $logger;
		$this->twig = $twig;
		$this->config_service = $config_service;
		$this->mail_addr_system_service = $mail_addr_system_service;
		$this->email_validate = $email_validate;
		$this->systems_service = $systems_service;

		$enc = getenv('SMTP_ENC') ?: 'tls';
		$transport = (new \Swift_SmtpTransport(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc))
			->setUsername(getenv('SMTP_USERNAME'))
			->setPassword(getenv('SMTP_PASSWORD'));
		$this->mailer = new \Swift_Mailer($transport);
		$this->mailer->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(100, 30));
		$this->mailer->getTransport()->stop();

		$this->converter = new HtmlConverter();
		$converter_config = $this->converter->getConfig();
		$converter_config->setOption('strip_tags', true);
		$converter_config->setOption('remove_nodes', 'img');
	}

	public function process(array $data):void
	{
		if ($this->has_data_error($data, 'mail_process'))
		{
			return;
		}

		$schema = $data['schema'];

		if ($this->has_from_address_error($data, 'mail_process'))
		{
			return;
		}

		$data['vars']['schema'] = $schema;

		$system = $this->systems_service->get_system($schema);

		$data['vars']['system'] = $system;

		if (isset($data['pre_html_template']))
		{
			try
			{
				$pre_html_template = $this->twig->createTemplate($data['pre_html_template']);
				$data['vars']['html'] = $pre_html_template->render($data['vars']);
				$data['vars']['text'] = $this->converter->convert($data['vars']['html']);
			}
			catch (\Exception $e)
			{
				$this->logger->error('Mail Queue Process, Pre HTML template err: ' .
					$e->getMessage() . ' ::: ' .
					json_encode($data),
					['schema' => $schema]);
				return;
			}
		}

		$template = $this->twig->load('mail/' . $data['template'] . '.twig');
		$subject = $template->renderBlock('subject', $data['vars']);
		$text = $template->renderBlock('text_body', $data['vars']);
		$html = $template->renderBlock('html_body', $data['vars']);

		$message = (new \Swift_Message())
			->setSubject($subject)
			->setBody($text)
			->setTo($data['to'])
			->setFrom($data['from'])
			->addPart($html, 'text/html');

		$headers = $message->getHeaders();

		if (isset($data['reply_to']))
		{
			$message->setReplyTo($data['reply_to']);
		}

		if (isset($data['cc']))
		{
			$message->setCc($data['cc']);
		}

		if (isset($data['vars']['et']))
		{
			$headers->addTextHeader('X-Eland', $data['vars']['et']);
		}

		try
		{
			if ($this->mailer->send($message, $failed_recipients))
			{
				$this->logger->info('mail queue process, sent to ' .
					json_encode($data['to']) . ' subject: ' . $subject,
					['schema' => $schema]);
			}
			else
			{
				$this->logger->error('mail queue process: failed sending message ' .
					json_encode($data) .
					' failed recipients: ' .
					json_encode($failed_recipients),
					['schema' => $schema]);
			}
		}
		catch (\Exception $e)
		{
			$err = $e->getMessage();
			$this->logger->error('mail queue process: ' . $err . ' | ' .
				json_encode($data),
				['schema' => $schema]);
		}

		$this->mailer->getTransport()->stop();
	}

	public function queue(array $data, int $priority):void
	{
		if ($this->has_data_error($data, 'mail_queue'))
		{
			return;
		}

		$schema = $data['schema'];

		if (isset($data['reply_to']))
		{
			if (is_array($data['reply_to']))
			{
				if (!count($data['reply_to']))
				{
					unset($data['reply_to']);
				}
			}
			else
			{
				unset($data['reply_to']);
			}
		}

		if (isset($data['reply_to']))
		{
			$data['from'] = $this->mail_addr_system->get_from($schema);
		}
 		else
		{
			$data['from'] = $this->mail_addr_system->get_noreply($schema);
		}

		if ($this->has_from_address_error($data, 'mail_queue'))
		{
			return;
		}

		if (isset($data['cc']))
		{
			if (is_array($data['cc']))
			{
				if (!count($data['cc']))
				{
					unset($data['cc']);
				}
			}
			else
			{
				unset($data['cc']);
			}
		}

		$reply_log = isset($data['reply_to']) ? ' reply-to: ' . json_encode($data['reply_to']) : '';

		foreach ($data['to'] as $email => $name)
		{
			if (!filter_var($email, FILTER_VALIDATE_EMAIL))
			{
				$this->logger->error('mail queue (validate): non-valid email address (not sent): ' .
					$email . ' data: ' . json_encode($data),
					['schema' => $schema]);
				continue;
			}

			$val_data = $data;
			$val_data['to'] = [$email => $name];

			$email_token = $this->email_validate->get_token($email, $schema, $data['template']);
			$val_data['vars']['et'] = $email_token;

			$this->queue->set('mail', $val_data, $priority);

			$this->logger->info('mail in queue with email token ' .
				$email_token .
				', template: ' . $val_data['template'] . ', from : ' .
				json_encode($val_data['from']) . ' to : ' . json_encode($val_data['to']) . ' ' .
				$reply_log .
				' priority: ' . $priority,
				['schema' => $schema]);
		}
	}

	protected function has_data_error(array $data, string $log_prefix):bool
	{
		if (!isset($data['schema']))
		{
			$this->logger->error($log_prefix .
				': no schema set. ' .
				json_encode($data));
			return true;
		}

		$schema = $data['schema'];

		if (!isset($data['template']))
		{
			$this->logger->error($log_prefix .
				': no template set ' .
				json_encode($data),
				['schema' => $schema]);
			return true;
		}

		if (!isset($data['vars']) || !is_array($data['vars']))
		{
			$this->logger->error($log_prefix .
				': no vars set ' .
				json_encode($data),
				['schema' => $schema]);
			return true;
		}

		if (!$this->config_service->get('mailenabled', $schema))
		{
			$this->logger->info($log_prefix .
				': mail functions are not enabled in config. ' .
				json_encode($data),
				['schema' => $schema]);
			return true;
		}

		if (!isset($data['to']) || !is_array($data['to']) || !count($data['to']))
		{
			$this->logger->error($log_prefix .
				': "To" addr is missing. ' .
				json_encode($data),
				['schema' => $schema]);
			return true;
		}

		return false;
	}

	protected function has_from_address_error(array $data, string $log_prefix):bool
	{
		if (!isset($data['from']) || !is_array($data['from']) || !count($data['from']))
		{
			$this->logger->error($log_prefix .
				': "From" addr is missing. ' .
				json_encode($data),
				['schema' => $data['schema']]);
			return true;
		}

		return false;
	}
}
