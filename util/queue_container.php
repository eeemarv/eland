<?php

namespace util;

use util\job_container;

class queue_container extends job_container
{
	private $data;
	private $queue_task;

	public function should_run()
	{
		$now = time();

		$omit_ary = [];

		foreach ($this->jobs as $queue)
		{
			if ($queue->get_next() > $now)
			{
				$omit_ary[] = $queue->get_class_name();
			}
		}

		$record = $this->app['queue']->get($omit_ary);

		if (!$record)
		{
			return false;
		}

		$topic = $record['topic'];
		$this->data = $record['data'];

		if (!isset($this->data['schema']))
		{
			error_log('no schema set for queue msg id : ' . $record['id'] . ' data: ' .
				json_encode($this->data) . ' topic: ' . $topic);

			return false;
		}

		if (!isset($this->jobs[$topic]))
		{
			error_log('Queue task not recognised: ' . json_encode($record));

			return false;
		}

		error_log('queue should run: ' . $topic . ' priority: ' . $record['priority'] . ' id: ' . $record['id']);

		$this->queue_task = $this->app['queue.' . $topic];

		return true;
	}

	public function run()
	{
		$this->queue_task->process($this->data);
		$this->queue_task->set_next();
		return $this;
	}
}
