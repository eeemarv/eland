<?php

namespace eland;

use Monolog\Logger;

class alert
{
	private $send_once;
	private $monolog;

	public function __construct(Logger $monolog)
	{
		$this->monolog = $monolog;
	} 

	private function add($type, $msg)
	{
		$url = $_SERVER[HTTP_HOST] . $_SERVER[REQUEST_URI];

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

		if (!isset($_SESSION['alert']) || !is_array($_SESSION['alert']))
		{
			$_SESSION['alert'] = [];
		}

		$_SESSION['alert'][] = [$type, $msg];
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
		if (!(isset($_SESSION['alert']) && is_array($_SESSION['alert']) && count($_SESSION['alert'])))
		{
			return;
		}

		while ($alert = array_pop($_SESSION['alert']))
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
		$is_set = (!isset($this->send_once) && isset($_SESSION['alert'])
			&& is_array($_SESSION['alert']) && count($_SESSION['alert'])) ? true : false;

		$this->send_once = true;

		return $is_set;
	}
}

