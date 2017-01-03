<?php

namespace eland;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Session;

class alert
{
	private $send_once;
	private $monolog;
	private $session;
	private $flashbag;

	public function __construct(Logger $monolog, Session $session)
	{
		$this->monolog = $monolog;
		$this->session = $session;
		$this->flashbag = $this->session->getFlashBag();
	} 

	private function add($type, $msg)
	{
		$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		if (is_array($msg))
		{
			$log = implode(' -- & ', $msg);
			$msg = implode('<br>', $msg);
			$this->monolog->debug('[alert ' . $type . ' ' . $url . '] ' . $log, ['alert_type' => $type]);
		}
		else
		{
			$this->monolog->debug('[alert ' . $type . ' ' . $url . '] ' . $msg, ['alert_type' => $type]);
		}

		$this->flashbag->add('alert', [$type, $msg]);
	}

	public function error($msg)
	{
		$this->add('error', $msg);
	}

	function success($msg)
	{
		$this->add('success', $msg);
	}

	public function info($msg)
	{
		$this->add('info', $msg);
	}

	public function warning($msg)
	{
		$this->add('warning', $msg);
	}

	public function render()
	{
		if (!$this->flashbag->has('alert'))
		{
			return;
		}

		foreach ($this->flashbag->get('alert') as $alert)
		{
			$alert[0] = ($alert[0] == 'error') ? 'danger' : $alert[0];

			echo '<div class="row">';
			echo '<div class="col-xs-12">';
			echo '<div class="alert alert-' . $alert[0] . ' alert-dismissible" role="alert">';
			echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
			echo '<span aria-hidden="true">&times;</span></button>';
			echo $alert[1] . '</div></div></div>';
		}
	}

	public function is_set()
	{
		$is_set = (!isset($this->send_once) && $this->flashbag->has('alert')) ? true : false;

		$this->send_once = true;

		return $is_set;
	}
}

