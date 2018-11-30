<?php

namespace service;

use Monolog\Logger;
use service\config;

class mail_addr_system
{
	protected $config;
	protected $monolog;

	public function __construct(Logger $monolog, config $config)
	{
		$this->monolog = $monolog;
		$this->config = $config;
	}

	public function get_from(string $schema):array
	{
		$mail = getenv('MAIL_FROM_ADDRESS');
		$mail = trim($mail);

		if ($this->validate($mail, 'from', $schema))
		{
			return get_mail_ary($mail, $schema);
		}

		return [];
	}

	public function get_noreply(string $schema):array
	{
		$mail = getenv('MAIL_NOREPLY_ADDRESS');
		$mail = trim($mail);

		if ($this->validate($mail, 'noreply', $schema))
		{
			return get_mail_ary($mail, $schema);
		}

		return [];
	}

	protected function get_mail_ary(string $mail, string $schema):array
	{
		return [$mail => $this->config->get('systemname', $schema)];
	}

	protected function validate(string $mail, string $name, string $schema):bool
	{
		if (filter_var($mail, FILTER_VALIDATE_EMAIL))
		{
			return true;
		}

		$this->monolog->error('Mail error: invalid "' .
			$name . '" mail address : ' . $mail,
			['schema' => $schema]
		);

		return false;
	}
}
