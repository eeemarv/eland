<?php

namespace eland\model;

use eland\model\task_interface;

abstract class task implements task_interface
{
	public function run()
	{
	}

	public function can_run()
	{
		return true;
	}

	public function should_run()
	{
		return true;
	}

	public function get_interval()
	{
		return 86400;
	}
}
