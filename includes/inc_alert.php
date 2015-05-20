<?php

class alert
{
	function add($type, $string)
	{
		if (!isset($_SESSION['alert']) || !is_array($_SESSION['alert']))
		{
			$_SESSION['alert'] = array();
		}		
		$_SESSION['alert'][] = array($type, $string);
	}

	function error($string)
	{
		$this->add('error', $string);
	}

	function success($string)
	{
		$this->add('success', $string);
	}

	function notice($string)
	{
		$this->add('notice', $string);
	}

	function warning($string)
	{
		$this->add('warning', $string);
	}

	function render()
	{
		if (!(isset($_SESSION['alert']) && is_array($_SESSION['alert']) && count($_SESSION['alert'])))
		{
			return;
		}

		while ($alert = array_pop($_SESSION['alert']))
		{
			$alert[0] = ($alert[0] == 'error') ? 'danger' : $alert[0];
			echo '<div class="alert alert-' . $alert[0] . ' alert-dismissible" role="alert">';
			echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
			echo '<span aria-hidden="true">&times;</span></button>';
			echo $alert[1] . '</div>';
		}
	}
}

