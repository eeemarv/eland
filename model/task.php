<?php

namespace eland\model;

use eland\model\task_interface;
use eland\schedule;

abstract class task implements task_interface
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
		$this->schedule->update();
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

	public function get_class_name()
	{
		$full = static::class;
		$pos = strrpos($full, '\\');

		if ($pos)
		{
			return substr($full, $pos + 1);
		}

		return $full;
	}
}
