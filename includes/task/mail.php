<?php

namespace eland\task;

use League\HTMLToMarkdown\HtmlConverter;
use eland\queue;
use Monolog\Logger;

class mail
{

	protected $converter;
	protected $mailer;
	protected $queue;
	protected $monolog;

	public function __construct(queue $queue, Logger $monolog)
	{
		$this->queue = $queue;
		$this->monolog = $monolog;

		$enc = getenv('SMTP_ENC') ?: 'tls';
		$transport = Swift_SmtpTransport::newInstance(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc)
			->setUsername(getenv('SMTP_USERNAME'))
			->setPassword(getenv('SMTP_PASSWORD'));

		$this->mailer = Swift_Mailer::newInstance($transport);

		$this->mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));

		$this->converter = new HtmlConverter();
		$this->converter->getConfig()->setOption('strip_tags', true);
	}

	/**
	 *
	 */
	public function run(array $data, bool $check_schema = false)
	{
		if ($check_schema)
		{
			if (!isset($data['schema']))
			{
				$app->monolog->error('mail error: mail in queue without schema');
				return;
			}

			$schema = $data['schema'];
			unset($data['schema']);

			if (!readconfigfromdb('mailenabled', $schema))
			{
				$m = 'Mail functions are not enabled. ' . "\n";
				echo $m;
				$this->monolog->error('mail: ' . $m);
				return ;
			}
		}

		if (!isset($data['subject']))
		{
			$this->monolog->error('mail error: mail without subject', ['schema' => $schema]);
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
				$this->monolog->error('mail error: mail without body content', ['schema' => $schema]);
			}
		}

		if (!$data['to'])
		{
			$this->monolog->error('mail error: mail without "to" | subject: ' . $data['subject'], ['schema' => $schema]);
			return;
		}

		if (!$data['from'])
		{
			$this->monolog->error('mail error: mail without "from" | subject: ' . $data['subject'], ['schema' => $schema]);
			return;
		} 

		$message = Swift_Message::newInstance()
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

		if ($this->mailer->send($message, $failed_recipients))
		{
			$this->monolog->info('mail: message send to ' . implode(', ', $data['to']) . ' subject: ' . $data['subject'], ['schema' => $schema]);
		}
		else
		{
			$this->monolog->error('mail error: failed sending message to ' . implode(', ', $data['to']) . ' subject: ' . $data['subject'], ['schema' => $schema]);
		}

		if ($failed_recipients)
		{
			$this->monolog->error('mail: failed recipients: ' . $failed_recipients, ['schema' => $schema]);
		}
	}

	public function queue(array $data)
	{

	} 
}
