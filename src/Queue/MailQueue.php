<?php declare(strict_types=1);

namespace App\Queue;

use App\HtmlProcess\HtmlToMarkdownConverter;
use App\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment as Twig;
use App\Service\ConfigService;
use App\Service\MailAddrSystemService;
use App\Service\EmailVerifyService;
use App\Service\QueueService;
use App\Service\SystemsService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Serializer\SerializerInterface;

class MailQueue implements QueueInterface
{
	public function __construct(
		protected QueueService $queue_service,
		protected LoggerInterface $logger,
		protected Twig $twig,
		protected ConfigService $config_service,
		protected MailAddrSystemService $mail_addr_system_service,
		protected EmailVerifyService $email_verify_service,
		protected SystemsService $systems_service,
		protected HtmlToMarkdownConverter $html_to_markdown_converter,
		protected MailerInterface $mailer,
		protected TransportInterface $transport,
		protected SerializerInterface $serializer
	)
	{
	}

	public function process(array $data):void
	{
		if ($this->has_data_error($data, 'mail_process'))
		{
			return;
		}

		$schema = $data['schema'];

		$data['vars']['schema'] = $schema;

		$system = $this->systems_service->get_system($schema);

		$data['vars']['system'] = $system;

		if (isset($data['pre_html_template']))
		{
			try
			{
				$pre_html_template = $this->twig->createTemplate($data['pre_html_template']);
				$data['vars']['html'] = $pre_html_template->render($data['vars']);
				$data['vars']['text'] = $this->html_to_markdown_converter->convert($data['vars']['html']);
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

		$to = $this->deserialize_address_ary($data['to']);
		$from = $this->deserialize_address_ary($data['from']);

		$email = new Email();
		$email->subject($subject);
		$email->text($text);
		$email->html($html);
		$email->to(...$to);
		$email->from(...$from);

		if (isset($data['reply_to']))
		{
			$reply_to = $this->deserialize_address_ary($data['reply_to']);
			$email->replyTo(...$reply_to);
		}

		if (isset($data['cc']))
		{
			$cc = $this->deserialize_address_ary($data['cc']);
			$email->cc(...$cc);
		}

		if (isset($data['vars']['et']))
		{
			$email->getHeaders()->addTextHeader('X-Eland', $data['vars']['et']);
		}

		try
		{
			$this->mailer->send($email);
			$this->logger->info('mail queue send: ' .
				json_encode($data),
				['schema' => $schema]);
		}
		catch (TransportExceptionInterface $e)
		{
			$err = $e->getMessage();
			$this->logger->error('mail queue process: ' . $err . ' | ' .
				json_encode($data),
				['schema' => $schema]);
		}

		if ($this->transport instanceof SmtpTransport)
		{
			$this->transport->stop();
		}
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
			$data['reply_to'] = $this->serialize_address_ary($data['reply_to']);

			$adr_from = $this->mail_addr_system_service->get_from($schema);
		}
 		else
		{
			$adr_from = $this->mail_addr_system_service->get_noreply($schema);
		}

		$data['from'] = $this->serialize_address_ary($adr_from);

		if ($this->has_from_address_error($data, 'mail_queue'))
		{
			return;
		}

		if (isset($data['cc']))
		{
			$data['cc'] = $this->serialize_address_ary($data['cc']);
		}

		$to = $data['to'];
		$data['to'] = [];

		foreach($to as $email_adr)
		{
			/** @var Address $email_adr */
			$email = $email_adr->getAddress();

			if (!filter_var($email, FILTER_VALIDATE_EMAIL))
			{
				$this->logger->error('mail queue (validate): non-valid email address (not sent): ' .
					$email . ' data: ' . json_encode($data),
					['schema' => $schema]);
				continue;
			}

			$data['to'][] = $this->serialize_address($email_adr);
		}

		if (count($data['to']) === 0)
		{
			return;
		}

		$email_token = $this->email_verify_service->get_token($email, $schema, $data['template']);
		if (count($data['to']) !== 1)
		{
			$email_token .= '-no-unique-to-addr';
		}
		$data['vars']['et'] = $email_token;

		$this->queue_service->set('mail', $data, $priority);

		$log_msg = 'mail in queue';
		$log_msg .= isset($email_token) ? ' with email token ' . $email_token : '';
		$log_msg .= ', ';
		$log_msg .= json_encode($data);
		$log_msg .= ', priority: ' . $priority;

		$this->logger->info($log_msg, ['schema' => $schema]);
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

		if (!$this->config_service->get_bool('mail.enabled', $schema))
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

	protected function serialize_address_ary(array $address_ary):array
	{
		$ary = [];
		foreach($address_ary as $adr)
		{
			$ary[] = $this->serialize_address($adr);
		}
		return $ary;
	}

	protected function deserialize_address_ary(array $serialized_address_ary):array
	{
		$ary = [];
		foreach($serialized_address_ary as $ser_adr)
		{
			$ary[] = $this->deserialize_address($ser_adr);
		}
		return $ary;
	}

	protected function serialize_address(Address $address):string
	{
		return $this->serializer->serialize($address, 'json');
	}

	protected function deserialize_address(string $json_address):Address
	{
		return $this->serializer->deserialize($json_address, Address::class, 'json');
	}
}
