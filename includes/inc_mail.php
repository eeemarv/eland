<?php

function sendmail()
{
	global $redis, $r, $db;

	for ($i = 0; $i < 50; $i++)
	{
		$mail = $redis->rpop('mail_q');

		if (!$mail)
		{
			break;
		}

		$from = getenv('MAIL_FROM_ADDRESS');
		$noreply = getenv('MAIL_NOREPLY_ADDRESS');

		$mail = json_decode($mail, true);
		$schema = $mail['schema'];

		if (!isset($transport) || !isset($mailer))
		{
			$transport = Swift_SmtpTransport::newInstance(getenv('SMTP_HOST'), getenv('SMTP_PORT'))
				->setUsername(getenv('SMTP_USERNAME'))
				->setPassword(getenv('SMTP_PASSWORD'));

			$mailer = Swift_Mailer::newInstance($transport);

			$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));
		}

		if (!isset($schema))
		{
			log_event('', 'mail', 'error: mail in queue without schema');
			continue;
		}

		if (!isset($mail['subject']))
		{
			log_event('', 'mail', 'error: mail without subject', $schema);
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
				log_event('', 'mail', 'error: mail without body content', $schema);
			}
		}

		if (isset($mail['to']))
		{
			if (!is_array($mail['to']))
			{
				$mail['to'] = explode(',', $mail['to']);
			}

			$mail_to = $mail['to'];
			$mail['to'] = array();

			foreach ($mail_to as $to)
			{
				$to = trim($to);

				if (ctype_digit($to))
				{
					$st = $db->prepare('select c.value
						from ' . $schema . '.contact c,
							' . $schema . '.type_contact tc,
							' . $schema . '.users u
						where c.id_type_contact = tc.id
							and c.id_user = ?
							and c.id_user = u.id
							and u.status in (1,2)
							and tc.abbrev = \'mail\'');

					$st->bindValue(1, $to);
					$st->execute();

					while ($row = $st->fetch())
					{
						if (!filter_var($row['value'], FILTER_VALIDATE_EMAIL))
						{
							log_event('', 'mail',
								'error: invalid "to" mail address : ' . $row['value'] . ', user id: ' . $to,
								$schema);
							continue;
						}

						$mail['to'][] = trim($row['value']);
					}
				}
				else if (filter_var($to, FILTER_VALIDATE_EMAIL))
				{
					$to = $db->fetchColumn('select c.value
						from ' . $schema . '.contact c,
						' . $schema . '.type_contact tc,
						' . $schema . '.users u
						where c.id_type_contact = tc.id
							and c.value = ?
							and c.id_user = u.id
							and u.status in (1,2)
							and tc.abbrev = \'mail\'', array($to));

					if (!$to)
					{
						log_event('', 'mail', 'error: no mail or user not active for mail : ' . $to, $schema);
						continue;
					}

					$mail['to'][] = trim($to);
				}
				else
				{
					log_event('', 'mail', 'error: invalid "to" : ' . $to, $schema);
				}
			}
		}

		if (!isset($mail['to']) || !count($mail['to']))
		{
			log_event('', 'mail', 'error: mail without "to" | subject: ' . $mail['subject'], $schema);
			continue;
		} 

		if (isset($mail['reply_to']))
		{
			if (!is_array($mail['reply_to']))
			{
				$mail['reply_to'] = explode(',', $mail['reply_to']);
			}

			$mail_reply_to = $mail['reply_to'];
			$mail['reply_to'] = array();

			foreach ($mail_reply_to as $reply_to)
			{
				$reply_to = trim($reply_to);

				if (ctype_digit($to))
				{
					$st = $db->prepare('select c.value
						from ' . $schema . '.contact c,
						' . $schema . '.type_contact tc,
						' . $schema . '.users u
						where c.id_type_contact = tc.id
							and c.id_user = ?
							and c.id_user = u.id
							and u.status in (1,2)
							and tc.abbrev = \'mail\'');

					$st->bindValue(1, $reply_to);
					$st->execute();

					while ($row = $st->fetch())
					{
						if (!filter_var($row['value'], FILTER_VALIDATE_EMAIL))
						{
							log_event('', 'mail',
								'error: invalid "reply to" mail address : ' . $row['value'] . ', user id: ' . $reply_to,
								$schema);
							continue;
						}

						$mail['reply_to'][] = trim($row['value']);
					}
				}
				else if (filter_var($reply_to, FILTER_VALIDATE_EMAIL))
				{
					$reply_to = $db->fetchColumn('select c.value
						from ' . $schema . '.contact c,
						' . $schema . '.type_contact tc,
						' . $schema . '.users u
						where c.id_type_contact = tc.id
							and c.value = ?
							and c.id_user = u.id
							and u.status in (1,2)
							and tc.abbrev = \'mail\'', array($reply_to));

					if (!$reply_to)
					{
						log_event('', 'mail', 'error: no mail or user not active for mail : ' . $reply_to, $schema);
						continue;
					}

					$mail['reply_to'][] = trim($reply_to);
				}
				else
				{
					log_event('', 'mail', 'error: invalid "reply to" : ' . $reply_to, $schema);
				}
			}
		}

		if (isset($mail['cc']))
		{
			if (!is_array($mail['cc']))
			{
				$mail['cc'] = array($mail['cc']);
			}

			$mail_cc = $mail['cc'];
			$mail['cc'] = array();

			foreach ($mail_cc as $cc)
			{
				$cc = trim($cc);

				if (ctype_digit($to))
				{
					$st = $db->prepare('select c.value
						from ' . $schema . '.contact c,
						' . $schema . '.type_contact tc,
						' . $schema . '.users u
						where c.id_type_contact = tc.id
							and c.id_user = ?
							and c.id_user = u.id
							and u.status in (1,2)
							and tc.abbrev = \'mail\'');

					$st->bindValue(1, $cc);
					$st->execute();

					while ($row = $st->fetch())
					{
						if (!filter_var($row['value'], FILTER_VALIDATE_EMAIL))
						{
							log_event('', 'mail',
								'error: invalid "cc" mail address : ' . $row['value'] . ', user id: ' . $cc,
								$schema);
							continue;
						}

						$mail['cc'][] = trim($row['value']);
					}
				}
				else if (filter_var($cc, FILTER_VALIDATE_EMAIL))
				{
					$cc = $db->fetchColumn('select c.value
						from ' . $schema . '.contact c,
						' . $schema . '.type_contact tc,
						' . $schema . '.users u
						where c.id_type_contact = tc.id
							and c.value = ?
							and c.id_user = u.id
							and u.status in (1,2)
							and tc.abbrev = \'mail\'', array($cc));

					if (!$cc)
					{
						log_event('', 'mail', 'error: no mail or user not active for mail : ' . $cc, $schema);
						continue;
					}

					$mail['cc'][] = trim($cc);
				}
				else
				{
					log_event('', 'mail', 'error: invalid "cc" : ' . $cc, $schema);
				}
			}
		}

		$subject = '[' . readconfigfromdb('systemtag', $schema) . '] ' . $mail['subject'];

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
			log_event('', 'mail', 'message send to ' . implode(', ', $mail['to']) . ' subject: ' . $mail['subject'], $schema);
		}
		else
		{
			log_event('', 'mail', 'failed sending message to ' . implode(', ', $mail['to']) . ' subject: ' . $mail['subject'], $schema);
		}

		if ($failed_recipients)
		{
			log_event('', 'mail', 'failed recipients: ' . $failed_recipients, $schema);
		}
	}
}

