<?php

namespace eland\util;

use Silex\Application;
use Symfony\Component\Finder\Finder;

class task_container
{
	private $task_type;
	private $tasks = [];
	private $task;

	public function __construct(Application $app, string $task_type)
	{
		$this->task_type = $task_type;
		$this->app = $app;

		error_log(':: task_type : ' . $this->task_type . ' :: ');

		$finder = new Finder();
		$finder->files()
			->in(__DIR__ . '/../' . $this->task_type)
			->name('*.php');

		foreach ($finder as $file)
		{
			$path = $file->getRelativePathname();

			$task = basename($path, '.php');

			$this->tasks[$task] = $app['eland.' . $this->task_type . '.' . $task];

			error_log('- ' . $task . ' : ' . $this->tasks[$task]->get_interval());
		}

		error_log(' - - - - - - - - - - - - - - - - -');
	}

	public function should_run()
	{
		foreach ($this->tasks as $task)
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
