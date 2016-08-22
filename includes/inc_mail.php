<?php

use League\HTMLToMarkdown\HtmlConverter;

function sendmail()
{
	global $r, $app;

	$text_converter = new HtmlConverter();
	$text_converter->getConfig()->setOption('strip_tags', true);

	$mail_ary = $app['eland.queue']->get('mail', 20);

	foreach ($mail_ary as $queue_record)
	{
		$mail = $queue_record['data'];

		$schema = $mail['schema'];
		unset($mail['schema']);

		if (!isset($schema))
		{
			$app['monolog']->error('mail error: mail in queue without schema');
			continue;
		}

		if (!readconfigfromdb('mailenabled', $schema))
		{
			$m = 'Mail functions are not enabled. ' . "\n";
			echo $m;
			$app['monolog']->error('mail: ' . $m);
			return ;
		}

		if (!isset($transport) || !isset($mailer))
		{
			$enc = getenv('SMTP_ENC') ?: 'tls';
			$transport = Swift_SmtpTransport::newInstance(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc)
				->setUsername(getenv('SMTP_USERNAME'))
				->setPassword(getenv('SMTP_PASSWORD'));

			$mailer = Swift_Mailer::newInstance($transport);

			$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));
		}

		if (!isset($mail['subject']))
		{
			$app['monolog']->error('mail error: mail without subject', ['schema' => $schema]);
			continue;
		}

		if (!isset($mail['text']))
		{
			if (isset($mail['html']))
			{
				$mail['text'] = $text_converter->convert($mail['html']);
			}
			else
			{
				$app['monolog']->error('mail error: mail without body content', ['schema' => $schema]);
			}
		}

		if (!$mail['to'])
		{
			$app['monolog']->error('mail error: mail without "to" | subject: ' . $mail['subject'], ['schema' => $schema]);
			continue;
		}

		if (!$mail['from'])
		{
			$app['monolog']->error('mail error: mail without "from" | subject: ' . $mail['subject'], ['schema' => $schema]);
			continue;
		} 

		$message = Swift_Message::newInstance()
			->setSubject($mail['subject'])
			->setBody($mail['text'])
			->setTo($mail['to'])
			->setFrom($mail['from']);

		if (isset($mail['html']))
		{
			$message->addPart($mail['html'], 'text/html');
		}

		if (isset($mail['reply_to']))
		{
			$message->setReplyTo($mail['reply_to']);
		}

		if (isset($mail['cc']))
		{
			$message->setCc($mail['cc']);
		}

		if ($mailer->send($message, $failed_recipients))
		{
			$app['monolog']->info('mail: message send to ' . implode(', ', $mail['to']) . ' subject: ' . $mail['subject'], ['schema' => $schema]);
		}
		else
		{
			$app['monolog']->error('mail error: failed sending message to ' . implode(', ', $mail['to']) . ' subject: ' . $mail['subject'], ['schema' => $schema]);
		}

		if ($failed_recipients)
		{
			$app['monolog']->error('mail: failed recipients: ' . $failed_recipients, ['schema' => $schema]);
		}
	}
}

