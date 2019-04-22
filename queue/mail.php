<?php

namespace queue;

use queue\queue_interface;
use League\HTMLToMarkdown\HtmlConverter;
use service\queue;
use Monolog\Logger;
use Twig_Environment as Twig;
use service\config;
use service\mail_addr_system;
use service\token;
use service\email_validate;

class mail implements queue_interface
{
	protected $converter;
	protected $mailer;
	protected $queue;
	protected $monolog;
	protected $twig;
	protected $config;
	protected $mail_addr_system;
	protected $email_validate;

	public function __construct(
		queue $queue,
		Logger $monolog,
		Twig $twig,
		config $config,
		mail_addr_system $mail_addr_system,
		email_validate $email_validate
	)
	{
		$this->queue = $queue;
		$this->monolog = $monolog;
		$this->twig = $twig;
		$this->config = $config;
		$this->mail_addr_system = $mail_addr_system;
		$this->email_validate = $email_validate;

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
				$this->monolog->error('Mail Queue Process, Pre HTML template err: ' .
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

		if (isset($data['reply_to']))
		{
			$message->setReplyTo($data['reply_to']);
		}

		if (isset($data['cc']))
		{
			$message->setCc($data['cc']);
		}

		try
		{
			if ($this->mailer->send($message, $failed_recipients))
			{
				$this->monolog->info('mail queue process, sent to ' .
					json_encode($data['to']) . ' subject: ' . $subject,
					['schema' => $schema]);
			}
			else
			{
				$this->monolog->error('mail queue process: failed sending message ' .
					json_encode($data) .
					' failed recipients: ' .
					json_encode($failed_recipients),
					['schema' => $schema]);
			}
		}
		catch (\Exception $e)
		{
			$err = $e->getMessage();
			$this->monolog->error('mail queue process: ' . $err . ' | ' .
				json_encode($data),
				['schema' => $schema]);
		}

		$this->mailer->getTransport()->stop();
	}

	public function queue(array $data, int $priority = 10000):void
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

		$data['vars']['email_token'] = '';

		foreach ($data['to'] as $email => $name)
		{
			if (!filter_var($email, FILTER_VALIDATE_EMAIL))
			{
				$this->monolog->error('mail queue (validate): non-valid email address (not sent): ' .
					$email . ' data: ' . json_encode($data),
					['schema' => $schema]);
				continue;
			}

			$token = $this->email_validate->get_token($email, $schema, $data['template']);
			$val_data = $data;
			$val_data['to'] = [$email => $name];
			$val_data['email_token'] = $token;

			$this->queue->set('mail', $val_data, $priority);

			$this->monolog->info('mail in queue with email token ' .
				$token .
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
			$this->monolog->error($log_prefix .
				': no schema set. ' .
				json_encode($data));
			return true;
		}

		$schema = $data['schema'];

		if (!isset($data['template']))
		{
			$this->monolog->error($log_prefix .
				': no template set ' .
				json_encode($data),
				['schema' => $schema]);
			return true;
		}

		if (!isset($data['vars']) || !is_array($data['vars']))
		{
			$this->monolog->error($log_prefix .
				': no vars set ' .
				json_encode($data),
				['schema' => $schema]);
			return true;
		}

		if (!$this->config->get('mailenabled', $schema))
		{
			$this->monolog->info($log_prefix .
				': mail functions are not enabled in config. ' .
				json_encode($data),
				['schema' => $schema]);
			return true;
		}

		if (!isset($data['to']) || !is_array($data['to']) || !count($data['to']))
		{
			$this->monolog->error($log_prefix .
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
			$this->monolog->error($log_prefix .
				': "From" addr is missing. ' .
				json_encode($data),
				['schema' => $data['schema']]);
			return true;
		}

		return false;
	}
}
