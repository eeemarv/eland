<?php declare(strict_types=1);

namespace App\Model;

use App\Model\Job;
use App\Model\TaskInterface;
use App\Service\Schedule;

abstract class Task extends Job implements TaskInterface
{
	protected $schedule;

	public function __construct(Schedule $schedule)
	{
		$this->schedule = $schedule;
	}

	public function should_run():bool
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

	public function run():void
	{
		error_log('>> ' . $this->schedule->get_id());

		$this->process();
	}

	public function process():void
	{
	}

	public function is_enabled():bool
	{
		return false;
	}

	public function get_interval():int
	{
		return 86400;
	}
}
