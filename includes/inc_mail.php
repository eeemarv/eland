<?php

function sendmail()
{
	global $redis, $r, $db;

	for ($i = 0; $i < 20; $i++)
	{
		$mail = $redis->rpop('mail_q');

		if (!$mail)
		{
			break;
		}

		if (!readconfigfromdb('mailenabled'))
		{
			$m = 'Mail functions are not enabled. ' . "\n";
			echo $m;
			log_event('', 'mail', $m);
			return ;
		}

		$from = getenv('MAIL_FROM_ADDRESS');
		$noreply = getenv('MAIL_NOREPLY_ADDRESS');

		$mail = json_decode($mail, true);
		$from_schema = $mail['from_schema'];
		$to_schema = $mail['to_schema'];

		if (!isset($transport) || !isset($mailer))
		{
			$enc = getenv('SMTP_ENC') ?: 'tls';
			$transport = Swift_SmtpTransport::newInstance(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc)
				->setUsername(getenv('SMTP_USERNAME'))
				->setPassword(getenv('SMTP_PASSWORD'));

			$mailer = Swift_Mailer::newInstance($transport);

			$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));
		}

		if (!isset($from_schema))
		{
			log_event('', 'mail', 'error: mail in queue without "from" schema');
			continue;
		}

		if (!isset($to_schema))
		{
			log_event('', 'mail', 'error: mail in queue without "to" schema');
			continue;
		}

		if (!isset($mail['subject']))
		{
			log_event('', 'mail', 'error: mail without subject', $to_schema);
			continue;
		}

		if (!isset($mail['text']))
		{
			if (isset($mail['html']))
			{
				$mail['text'] = strip_tags($mail['html']);
			}
			else
			{
				log_event('', 'mail', 'error: mail without body content', $to_schema);
			}
		}

		$mail['to'] = getmailadr($mail['to'], $to_schema);

		if (!count($mail['to']))
		{
			log_event('', 'mail', 'error: mail without "to" | subject: ' . $mail['subject'], $to_schema);
			continue;
		} 

		if (isset($mail['reply_to']))
		{
			$mail['reply_to'] = getmailadr($mail['reply_to'], $from_schema);

			if (!count($mail['reply_to']))
			{
				log_event('', 'mail', 'error: invalid "reply to" : ' . $mail['subject'], $to_schema);
				unset($mail['reply_to']);
			}
		}

		if (isset($mail['cc']))
		{
			$mail['cc'] = getmailadr($mail['cc'], $to_schema);

			if (!count($mail['cc']))
			{
				log_event('', 'mail', 'error: invalid "reply to" : ' . $mail['subject'], $to_schema);
				unset($mail['cc']);
			}
		}

		$subject = '[' . readconfigfromdb('systemtag', $to_schema) . '] ' . $mail['subject'];

		$message = Swift_Message::newInstance()
			->setSubject($subject)
			->setBody($mail['text'])
			->setTo($mail['to']);

		if (isset($mail['html']))
		{
			$message->addPart($mail['html'], 'text/html');
		}

		if (isset($mail['reply_to']))
		{
			$message->setFrom($from)
				->setReplyTo($mail['reply_to']);
		}
		else
		{
			$message->setFrom($noreply);
		}

		if (isset($mail['cc']))
		{
			$message->setCc($mail['cc']);
		}

		if ($mailer->send($message, $failed_recipients))
		{
			log_event('', 'mail', 'message send to ' . implode(', ', $mail['to']) . ' subject: ' . $mail['subject'], $to_schema);
		}
		else
		{
			log_event('', 'mail', 'failed sending message to ' . implode(', ', $mail['to']) . ' subject: ' . $mail['subject'], $to_schema);
		}

		if ($failed_recipients)
		{
			log_event('', 'mail', 'failed recipients: ' . $failed_recipients, $to_schema);
		}
	}
}

