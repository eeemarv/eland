<?php

namespace eland;

use eland\cache;
use Predis\Client as Redis;

class schedule
{
	private $cache;
	private $redis;

	private $tasks;
	private $time;
	private $next;
	private $interval;
	private $id;

	public function __construct(cache $cache, Redis $redis)
	{
		$this->cache = $cache;
		$this->redis = $redis;

		$this->tasks = $this->cache->get('tasks');
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

	public function should_run()
	{
		if (!isset($this->tasks[$this->id]) || !$this->tasks[$this->id])
		{
			error_log('insert task: ' . $this->id . ' PID: ' . getmypid() . ' uid: ' . getmyuid() . ' inode: ' . getmyinode());

			$this->tasks[$this->id] = gmdate('Y-m-d H:i:s', $this->time + mt_rand(60, 900));

			$this->cache->set('tasks', $this->tasks);

			return false;
		}

		$last = strtotime($this->tasks[$this->id] . ' UTC');

		// test 12 min
		if (($this->time - $last) < 720)
		{

		//	error_log('blocked lt 720: ' . $this->id . ', last: ' . $last . ' diff: ' . ($this->time - $last) . ' PID: ' . getmypid() . ' uid: ' . getmyuid() . ' inode: ' . getmyinode());

			return false;
		}

		$this->next = $last + $this->interval;

		if ($this->next < $this->time)
		{
			$next = ((($this->time - $this->next) > 43200) || ($this->interval < 43201)) ? $this->time : $this->next;

			$next = gmdate('Y-m-d H:i:s', $next);

			$this->tasks[$this->id] = $next;

			$this->cache->set('tasks', $this->tasks);

			error_log('update & run: ' . $this->id . ' PID: ' . getmypid() . ' uid: ' . getmyuid() . ' inode: ' . getmyinode());

			return true;
		}

		return false;
	}
}
