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

	protected function add(string $type, $msg):void
	{
		$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$log_ary = [
			'schema'		=> $this->schema,
			'alert_type'	=> $type,
		];

		if (is_array($msg))
		{
			$log = implode(' -- & ', $msg);
			$msg = implode('<br>', $msg);
			$this->monolog->debug('[alert ' . $type . ' ' . $url . '] ' . $log,
				$log_ary);
		}
		else
		{
			$this->monolog->debug('[alert ' . $type . ' ' . $url . '] ' . $msg, $log_ary);
		}

		$this->flashbag->add('alert', [$type, $msg]);
	}

	public function error($msg):void
	{
		$this->add('error', $msg);
	}

	function success($msg):void
	{
		$this->add('success', $msg);
	}

	public function info($msg):void
	{
		$this->add('info', $msg);
	}

	public function warning($msg):void
	{
		$this->add('warning', $msg);
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
			$alert[0] = $alert[0] === 'error' ? 'danger' : $alert[0];

			$out .= '<div class="row">';
			$out .= '<div class="col-xs-12">';
			$out .= '<div class="alert alert-' . $alert[0] . ' alert-dismissible" role="alert">';
			$out .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
			$out .= '<span aria-hidden="true">&times;</span></button>';
			$out .= $alert[1] . '</div></div></div>';
		}

		return $out;
	}
}
