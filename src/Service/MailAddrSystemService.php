<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use App\Service\ConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

class MailAddrSystemService
{
	public function __construct(
		protected LoggerInterface $logger,
		protected ConfigService $config_service,
        #[Autowire('%env(MAIL_FROM_ADDRESS)%')]
        protected string $env_mail_from_address,
        #[Autowire('%env(MAIL_NOREPLY_ADDRESS)%')]
        protected string $env_mail_noreply_address
	)
	{
	}

	public function get_from(string $schema):array
	{
		$mail_ary = [$this->env_mail_from_address];
		return $this->get_validated_ary($mail_ary, 'from', $schema);
	}

	public function get_noreply(string $schema):array
	{
		$mail_ary = [$this->env_mail_noreply_address];
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
				$out[] = new Address($mail, $this->config_service->get_str('system.name', $schema));
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
