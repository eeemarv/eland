<?php declare(strict_types=1);

namespace App\Util;

use Util\JobContainerUtil;

class TaskContainerUtil extends JobContainerUtil
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
