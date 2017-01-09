<?php

namespace eland\util;

use eland\util\job_container;

class task_container extends job_container
{
	private $task;

	public function should_run()
	{
		foreach ($this->jobs as $task)
		{
			if ($task->should_run())
			{
				$this->task = $task;
				return true;
			}
		}

		return false;
	}

	public function run()
	{
		$this->task->run();
		return $this;
	}
}
