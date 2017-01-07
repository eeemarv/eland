<?php

namespace eland;

use eland\cache;

class schedule
{
	private $cache;

	private $tasks;
	private $time;
	private $next;
	private $interval;
	private $id;

	public function __construct(cache $cache)
	{
		$this->cache = $cache;

		$this->tasks = $this->cache->get('tasks');

		if (!count($this->tasks))
		{
			$cronjob_ary = $this->cache->get('cronjob_ary');

			foreach ($cronjob_ary as $cronjob => $ary)
			{
				$task = str_replace('_cronjob', '', $cronjob);
				$this->tasks[$task] = $ary['event_time'];
			}

			$this->cache->set('tasks', $this->tasks);
		}
	}

	public function set_time(int $time = 0)
	{
		$this->time = $time ? $time : time();
		return $this;
	}

	public function get_time()
	{
		if (!isset($this->time))
		{
			$this->time = time();
		}

		return $this->time();
	}

	public function set_id(string $id)
	{
		$this->id = $id;
		return $this;
	}

	public function get_id()
	{
		return $this->id;
	}

	public function set_interval(int $interval = 0)
	{
		$this->interval = $interval;
		return $this;
	}

	public function get_interval()
	{
		return $this->interval;
	}

	public function exists()
	{
		return isset($this->tasks[$this->id]) ? true : false;
	}

	public function should_run()
	{
		$last = strtotime($this->tasks[$this->id] . ' UTC');

		$this->next = $last + $this->interval;

		if ($this->next < $this->time)
		{
			return true;
		}

		return false;
	}

	public function update()
	{
		$next = ((($this->time - $this->next) > 43200) || ($this->interval < 43201)) ? $this->time : $this->next;

		$next = gmdate('Y-m-d H:i:s', $next);

		unset($this->tasks[$this->id]);

		$this->tasks[$this->id] = $next;

		$this->cache->set('tasks', $this->tasks);

		return $this;
	}
}
