<?php

namespace eland;

class alert
{
	private $send_once;

	function add($type, $msg)
	{
		if (is_array($msg))
		{
			$msg = implode('<br>', $msg);
		}

		if (!isset($_SESSION['alert']) || !is_array($_SESSION['alert']))
		{
			$_SESSION['alert'] = [];
		}		
		$_SESSION['alert'][] = [$type, $msg];
	}

	function error($msg)
	{
		$this->add('error', $msg);
	}

	function success($msg)
	{
		$this->add('success', $msg);
	}

	function info($msg)
	{
		$this->add('info', $msg);
	}

	function warning($msg)
	{
		$this->add('warning', $msg);
	}

	function render()
	{
		if (!(isset($_SESSION['alert']) && is_array($_SESSION['alert']) && count($_SESSION['alert'])))
		{
			return;
		}

		while ($alert = array_pop($_SESSION['alert']))
		{
			echo '<div class="row">';
			echo '<div class="col-xs-12">';
			$alert[0] = ($alert[0] == 'error') ? 'danger' : $alert[0];
			echo '<div class="alert alert-' . $alert[0] . ' alert-dismissible" role="alert">';
			echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
			echo '<span aria-hidden="true">&times;</span></button>';
			echo $alert[1] . '</div></div></div>';
		}
	}

	function is_set()
	{
		$is_set = (!isset($this->send_once) && isset($_SESSION['alert'])
			&& is_array($_SESSION['alert']) && count($_SESSION['alert'])) ? true : false;
		$this->send_once = true;
		return $is_set;
	}
}

