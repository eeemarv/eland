<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use App\Service\ConfigService;

class MailAddrSystemService
{
	protected ConfigService $config_service;
	protected LoggerInterface $logger;

	public function __construct(
		LoggerInterface $logger,
		ConfigService $config_service
	)
	{
		$this->logger = $logger;
		$this->config_service = $config_service;
	}

	public function get_from(string $schema):array
	{
		$mail_ary = [getenv('MAIL_FROM_ADDRESS')];
		return $this->get_validated_ary($mail_ary, 'from', $schema);
	}

	public function get_noreply(string $schema):array
	{
		$mail_ary = [getenv('MAIL_NOREPLY_ADDRESS')];
		return $this->get_validated_ary($mail_ary, 'noreply', $schema);
	}

	public function get_support(string $schema):array
	{
		$mail_ary = $this->config_service->get_ary('mail.addresses.support', $schema);
		return $this->get_validated_ary($mail_ary, 'support', $schema);
	}

	public function get_admin(string $schema):array
	{
		$mail_ary = $this->config_service->get_ary('mail.addresses.admin', $schema);
		return $this->get_validated_ary($mail_ary, 'admin', $schema);
	}

	protected function get_validated_ary(
		array $mail_ary,
		string $mail_id,
		string $schema
	):array
	{
		$out = [];

		foreach ($mail_ary as $mail)
		{
			if (!$mail)
			{
				continue;
			}

			$mail = trim($mail);

			if ($this->validate($mail, $mail_id, $schema))
			{
				$out[$mail] = $this->config_service->get('systemname', $schema);
			}
		}

		return $out;
	}

	protected function validate(string $mail, string $name, string $schema):bool
	{
		if (filter_var($mail, FILTER_VALIDATE_EMAIL))
		{
			return true;
		}

		$this->logger->error('Mail Addr System: invalid "' .
			$name . '" mail address : ' . $mail,
			['schema' => $schema]
		);

		return false;
	}
}
