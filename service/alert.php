<?php declare(strict_types=1);

namespace service;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Session;

class alert
{
	protected $monolog;
	protected $session;
	protected $flashbag;
	protected $schema;

	public function __construct(
		Logger $monolog,
		Session $session,
		string $schema
	)
	{
		$this->monolog = $monolog;
		$this->session = $session;
		$this->schema = $schema;
		$this->flashbag = $this->session->getFlashBag();
	}

	protected function add(string $type, $message):void
	{
		$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$log_ary = [
			'schema'		=> $this->schema,
			'alert_type'	=> $type,
		];

		if (is_array($message))
		{
			$log = implode(' -- & ', $message);
			$message = implode('<br>', $message);
			$this->monolog->debug('[alert ' . $type . ' ' . $url . '] ' . $log,
				$log_ary);
		}
		else
		{
			$this->monolog->debug('[alert ' . $type . ' ' . $url . '] ' . $message, $log_ary);
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

	public function get():string
	{
		if (!$this->flashbag->has('alert'))
		{
			return '';
		}

		$out = '';

		foreach ($this->flashbag->get('alert') as $alert)
		{
			$class = $alert['type'] === 'error' ? 'danger' : $alert['type'];

			$out .= '<div class="row">';
			$out .= '<div class="col-xs-12">';
			$out .= '<div class="alert alert-' . $class . ' alert-dismissible" role="alert">';
			$out .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
			$out .= '<span aria-hidden="true">&times;</span></button>';
			$out .= $alert['message'] . '</div></div></div>';
		}

		return $out;
	}

	public function get_ary():array
	{
		if (!$this->flashbag->has('alert'))
		{
			return [];
		}

		return $this->flashbag->get('alert');
	}
}
