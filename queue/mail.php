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

	/**
	 *
	 */
	public function process(array $data):void
	{
		if (!isset($data['schema']))
		{
			$app->monolog->error('mail queue proces: no schema. ' .
				json_encode($data));
			return;
		}

		$sch = $data['schema'];

		if (!$this->config->get('mailenabled', $sch))
		{
			$m = 'E-mail functions are not enabled. ' . "\n";
			echo $m;
			$this->monolog->error('mail queue proces: mail functions not enabled. ' .
				json_encode($data),
				['schema' => $sch]);
			return ;
		}

		if (isset($data['template']) && isset($data['vars']))
		{
			$template_subject = $this->twig->loadTemplate('mail/' . $data['template'] . '.subject.twig');
			$template_html = $this->twig->loadTemplate('mail/' . $data['template'] . '.html.twig');
			$template_text = $this->twig->loadTemplate('mail/' . $data['template'] . '.text.twig');

			$data['subject']  = $template_subject->render($data['vars']);
			$data['text'] = $template_text->render($data['vars']);
			$data['html'] = $template_html->render($data['vars']);
		}
		else if (isset($data['template_from_config']) && isset($data['vars']))
		{
			$template = $this->config->get($data['template_from_config'], $sch);

			if (!$template)
			{
				$this->monolog->error('mail queue process: no template set in config. ' .
					json_encode($data),
					['schema' => $sch]);
				return;
			}

			try
			{
				$template_subject = $this->twig->loadTemplate('mail/' . $data['template_from_config'] . '.subject.twig');
				$template_html = $this->twig->createTemplate($template);

				$data['subject']  = $template_subject->render($data['vars']);
				$data['text'] = $this->converter->convert($data['html']);
				$data['html'] = $template_html->render($data['vars']);
			}
			catch (Exception $e)
			{
				$this->monolog->error('mail queue process, config template err: ' .
					$e->getMessage() . ' ::: ' .
					json_encode($data),
					['schema' => $sch]);
				return;
			}
		}
		else
		{
			if (!isset($data['subject']))
			{
				$this->monolog->error('mail queue process: mail without subject' .
					json_encode($data),
					['schema' => $sch]);
				return;
			}

			if (!isset($data['text']))
			{
				if (isset($data['html']))
				{
					$data['text'] = $this->converter->convert($data['html']);
				}
				else
				{
					$this->monolog->error('mail queue process: mail without body content. ' .
						json_encode($data),
						['schema' => $sch]);
					return;
				}
			}
		}

		if (!isset($data['to']) || !is_array($data['to']) || !count($data['to']))
		{
			$this->monolog->error('mail queue process: mail without "to" ' .
				json_encode($data),
				['schema' => $sch]);
			return;
		}

		if (!$data['from'])
		{
			$this->monolog->error('mail queue process: mail without "from" ' .
				json_encode($data),
				['schema' => $sch]);
			return;
		}

		$message = (new \Swift_Message())
			->setSubject($data['subject'])
			->setBody($data['text'])
			->setTo($data['to'])
			->setFrom($data['from']);

		if (isset($data['html']))
		{
			$message->addPart($data['html'], 'text/html');
		}

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
				$this->monolog->info('mail queue process: sent ' .
					json_encode($data['to']) . ' subject: ' . $data['subject'],
					['schema' => $sch]);
			}
			else
			{
				$this->monolog->error('mail queue process: failed sending message ' .
					json_encode($data),
					['schema' => $sch]);
				$this->monolog->error('mail queue process, failed recipients: ' .
					json_encode($failed_recipients),
					['schema' => $sch]);
			}
		}
		catch (Exception $e)
		{
			$err = $e->getMessage();
			error_log('mail queue process: ' . $err);
			$this->monolog->error('mail queue process: ' . $err . ' | ' .
				json_encode($data),
				['schema' => $sch]);
		}

		$this->mailer->getTransport()->stop();
	}

	public function queue(array $data, int $priority = 10000):void
	{
		if (!isset($data['schema']))
		{
			$this->monolog->error('mail queue: no schema set. ' . json_encode($data));
			return;
		}

		$data['vars']['validate_param'] = '';

		if (!$this->config->get('mailenabled', $data['schema']))
		{
			$this->monolog->info('mail queue: Mail functions are not enabled. ' .
				json_encode($data),
				['schema' => $data['schema']]);
			return;
		}

		if (!isset($data['template']) && !isset($data['template_from_config']))
		{
			if (!isset($data['subject']) || $data['subject'] == '')
			{
				$this->monolog->error('mail queue: Subject is missing. ' .
					json_encode($data),
					['schema' => $data['schema']]);
				return;
			}

			if ((!isset($data['text']) || $data['text'] == '')
				&& (!isset($data['html']) || $data['html'] == ''))
			{
				$this->monolog->error('mail queue: body (text or html) is missing. ' .
					json_encode($data),
					['schema' => $data['schema']]);
				return;
			}

			$data['subject'] = '[' . $this->config->get('systemtag', $data['schema']) . '] ' . $data['subject'];
		}

		if (!isset($data['to']) || !is_array($data['to']) || !count($data['to']))
		{
			$this->monolog->error('mail queue: "To" addr is missing. ' .
				json_encode($data), ['schema' => $data['schema']]);
			return;
		}

		$validate_ary = [];

		if (isset($data['validate_email']) && isset($data['template']))
		{
			foreach ($data['to'] as $email => $name)
			{
				if (!filter_var($email, FILTER_VALIDATE_EMAIL))
				{
					continue;
				}

				if (!$this->email_validate->is_validated($email, $data['schema']))
				{
					$token = $this->email_validate->get_token($email, $data['schema'], $data['template']);

					$validate_ary[$email] = $token;
				}
			}
		}

		if (isset($data['reply_to']) && is_array($data['reply_to']) && count($data['reply_to']))
		{
			$data['from'] = $this->mail_addr_system->get_from($data['schema']);
		}
 		else
		{
			$data['from'] = $this->mail_addr_system->get_noreply($data['schema']);
		}

		if (!count($data['from']))
		{
			$this->monolog->error('mail queue: no from field. ' .
				json_encode($data), ['schema' => $data['schema']]);
			return;
		}

		if (!(isset($data['cc']) && is_array($data['cc'] && count($data['cc']))))
		{
			unset($data['cc']);
		}

		$reply = (isset($data['reply_to'])) ? ' reply-to: ' . json_encode($data['reply_to']) : '';

		foreach ($validate_ary as $email_to => $validate_token)
		{
			$val_data = $data;

			$val_data['to'] = [$email_to => $data['to'][$email]];
			$val_data['vars']['validate_param'] = '&ev=' . $validate_token;

			unset($data['to'][$email_to]);

			$this->queue->set('mail', $val_data, $priority);

			$this->monolog->info('mail: Mail in queue with validate token ' . $validate_token .
				', subject: ' .
				($data['subject'] ?? '(template)') . ', from : ' .
				json_encode($data['from']) . ' to : ' . json_encode($data['to']) . ' ' .
				$reply . ' priority: ' . $priority, ['schema' => $data['schema']]);
		}

		if (!isset($data['to']) || !$data['to'])
		{
			return;
		}

		$this->queue->set('mail', $data, $priority);

		$this->monolog->info('mail: Mail in queue, subject: ' .
			($data['subject'] ?? '(template)') . ', from : ' .
			json_encode($data['from']) . ' to : ' . json_encode($data['to']) . ' ' .
			$reply . ' priority: ' . $priority, ['schema' => $data['schema']]);
	}

	public function get_interval():int
	{
		return 5;
	}
}
