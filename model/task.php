<?php

namespace model;

use model\job;
use model\task_interface;
use service\schedule;

abstract class task extends job implements task_interface
{
	protected $schedule;

	public function __construct(schedule $schedule)
	{
		$this->schedule = $schedule;
	}

	public function should_run()
	{
		if (!$this->is_enabled())
		{
			return false;
		}

		return $this->schedule->set_time()
			->set_id($this->get_class_name())
			->set_interval($this->get_interval())
			->should_run();
	}

	public function run()
	{
		error_log('>> ' . $this->schedule->get_id());

		$this->process();
		return $this;
	}

	public function process()
	{
	}

	public function is_enabled()
	{
		return true;
	}

	public function get_interval()
	{
		return 86400;
	}
}
