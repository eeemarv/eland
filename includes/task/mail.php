<?php

namespace eland\task;

use League\HTMLToMarkdown\HtmlConverter;
use eland\queue;
use Monolog\Logger;
use eland\this_group;
use eland\mailaddr;

class mail
{
	protected $converter;
	protected $mailer;
	protected $queue;
	protected $monolog;
	protected $this_group;
	protected $mailaddr;

	public function __construct(queue $queue, Logger $monolog, this_group $this_group, mailaddr $mailaddr)
	{
		$this->queue = $queue;
		$this->monolog = $monolog;
		$this->this_group = $this_group;
		$this->mailaddr = $mailaddr;

		$enc = getenv('SMTP_ENC') ?: 'tls';
		$transport = \Swift_SmtpTransport::newInstance(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc)
			->setUsername(getenv('SMTP_USERNAME'))
			->setPassword(getenv('SMTP_PASSWORD'));

		$this->mailer = \Swift_Mailer::newInstance($transport);

		$this->mailer->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(100, 30));

		$this->converter = new HtmlConverter();
		$this->converter->getConfig()->setOption('strip_tags', true);
	}

	/**
	 *
	 */
	public function process(array $data)
	{
		if (!isset($data['schema']))
		{
			$app->monolog->error('mail error: mail in queue without schema');
			return;
		}

		$sch = $data['schema'];
		unset($data['schema']);

		if (!readconfigfromdb('mailenabled', $sch))
		{
			$m = 'Mail functions are not enabled. ' . "\n";
			echo $m;
			$this->monolog->error('mail: ' . $m);
			return ;
		}

		if (!isset($data['subject']))
		{
			$this->monolog->error('mail error: mail without subject', ['schema' => $sch]);
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
				$this->monolog->error('mail error: mail without body content', ['schema' => $sch]);
				return;
			}
		}

		if (!$data['to'])
		{
			$this->monolog->error('mail error: mail without "to" | subject: ' . $data['subject'], ['schema' => $sch]);
			return;
		}

		if (!$data['from'])
		{
			$this->monolog->error('mail error: mail without "from" | subject: ' . $data['subject'], ['schema' => $sch]);
			return;
		} 

		$message = \Swift_Message::newInstance()
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
			$this->monolog->info('mail: message send to ' . implode(', ', $data['to']) . ' subject: ' . $data['subject'], ['schema' => $sch]);
		}
		else
		{
			$this->monolog->error('mail error: failed sending message to ' . implode(', ', $data['to']) . ' subject: ' . $data['subject'], ['schema' => $sch]);
		}

		if ($failed_recipients)
		{
			$this->monolog->error('mail: failed recipients: ' . $failed_recipients, ['schema' => $sch]);
		}
	}

	public function queue(array $data, $priority = 100)
	{
		// only the interlets transactions receiving side has a different schema

		$data['schema'] = $data['schema'] ?? $this->this_group->get_schema();

		if (!readconfigfromdb('mailenabled', $data['schema']))
		{
			$m = 'Mail functions are not enabled. ' . "\n";
			$this->monolog->info('mail: ' . $m, ['schema' => $data['schema']]);
			return $m;
		}

		if (!isset($data['subject']) || $data['subject'] == '')
		{
			$m = 'Mail "subject" is missing.';
			$this->monolog->error('mail: '. $m, ['schema' => $data['schema']]);
			return $m;
		}

		if ((!isset($data['text']) || $data['text'] == '')
			&& (!isset($data['html']) || $data['html'] == ''))
		{
			$m = 'Mail "body" (text or html) is missing.';
			$this->monolog->error('mail: ' . $m, ['schema' => $data['schema']]);
			return $m;
		}

		if (!isset($data['to']) || !$data['to'])
		{
			$m = 'Mail "to" is missing for "' . $data['subject'] . '"';
			$this->monolog->error('mail: ' . $m, ['schema' => $data['schema']]);
			return $m;
		}

		$data['to'] = $this->mailaddr->get($data['to']);

		if (!count($data['to']))
		{
			$m = 'error: mail without "to" | subject: ' . $data['subject'];
			$this->monolog->error('mail: ' . $m);
			return $m;
		} 

		if (isset($data['reply_to']))
		{
			$data['reply_to'] = $this->mailaddr->get($data['reply_to']);

			if (!count($data['reply_to']))
			{
				$this->monolog->error('mail: error: invalid "reply to" : ' . $data['subject']);
				unset($data['reply_to']);
			}

			$data['from'] = $this->mailaddr->get('from', $data['schema']);
		}
		else
		{
			$data['from'] = $this->mailaddr->get('noreply', $data['schema']);
		}

		if (!count($data['from']))
		{
			$m = 'error: mail without "from" | subject: ' . $data['subject'];
			$this->monolog->error('mail: ' . $m);
			return $m;
		}

		if (isset($data['cc']))
		{
			$data['cc'] = $this->mailaddr->get($data['cc']);

			if (!count($data['cc']))
			{
				$this->monolog->error('mail error: invalid "reply to" : ' . $data['subject']);
				unset($data['cc']);
			}
		}

		$data['subject'] = '[' . readconfigfromdb('systemtag', $data['schema']) . '] ' . $data['subject'];

		$error = $this->queue->set('mail', $data, $priority);

		if (!$error)
		{
			$reply = (isset($data['reply_to'])) ? ' reply-to: ' . json_encode($data['reply_to']) : '';

			$this->monolog->info('mail: Mail in queue, subject: ' .
				$data['subject'] . ', from : ' .
				json_encode($data['from']) . ' to : ' . json_encode($data['to']) . $reply, ['schema' => $data['schema']]);
		}
	} 
}
