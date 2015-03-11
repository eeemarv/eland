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

	function add_error($string)
	{
		$this->add('error', $string);
	}

	function add_success($string)
	{
		$this->add('success', $string);
	}

	function add_notice($string)
	{
		$this->add('notice', $string);
	}

	function add_warning($string)
	{
		$this->add('warning', $string);
	}

	function render()
	{
		if (!($_SESSION['alert'] && count($_SESSION['alert'])))
		{
			return;
		}

		while ($alert = array_pop($_SESSION['alert']))
		{
			$alert[0] = ($alert[0] == 'error') ? 'danger' : $alert[0];
			echo '<p class="alert alert-' . $alert[0] . '" role="alert">' . $alert[1] . '</p>';
		}
	}
}

