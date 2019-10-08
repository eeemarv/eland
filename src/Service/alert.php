<?php declare(strict_types=1);

namespace App\Service;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class alert
{
	protected $request;
	protected $monolog;
	protected $session;
	protected $flashbag;
	protected $schema;

	public function __construct(
		Request $request,
		Logger $monolog,
		Session $session,
		string $schema
	)
	{
		$this->request = $request;
		$this->monolog = $monolog;
		$this->session = $session;
		$this->schema = $schema;
		$this->flashbag = $this->session->getFlashBag();
	}

	protected function add(string $type, $message):void
	{
		$uri = $this->request->getRequestUri();

		$log_ary = [
			'schema'		=> $this->schema,
			'alert_type'	=> $type,
		];

		if (is_array($message))
		{
			$log = implode(' -- & ', $message);
			$message = implode('<br>', $message);
			$this->monolog->debug('[alert ' . $type . ' ' . $uri . '] ' . $log,
				$log_ary);
		}
		else
		{
			$this->monolog->debug('[alert ' . $type . ' ' . $uri . '] ' . $message, $log_ary);
		}

		$this->flashbag->add('alert', [
			'type' 		=> $type,
			'message'	=> $message,
		]);
	}

	public function error($message):void
	{
		$this->add('error', $message);
	}

	function success($message):void
	{
		$this->add('success', $message);
	}

	public function info($message):void
	{
		$this->add('info', $message);
	}

	public function warning($message):void
	{
		$this->add('warning', $message);
	}
}
