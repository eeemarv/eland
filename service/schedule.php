<?php

namespace service;

use service\cache;
use Predis\Client as Redis;

class schedule
{
	protected $cache;
	protected $redis;

	protected $tasks;
	protected $time;
	protected $next;
	protected $interval;
	protected $id;

	public function __construct(cache $cache, Redis $redis)
	{
		$this->cache = $cache;
		$this->redis = $redis;

		$this->tasks = $this->cache->get('tasks');
	}

	public function set_time():self
	{
		$this->time = time();
		return $this;
	}

	public function get_time():int
	{
		if (!isset($this->time))
		{
			$this->time = time();
		}

		return $this->time();
	}

	public function set_id(string $id):self
	{
		$this->id = $id;
		return $this;
	}

	public function get_id():string
	{
		return $this->id;
	}

	public function set_interval(int $interval):self
	{
		$this->interval = $interval;
		return $this;
	}

	public function get_interval():int
	{
		return $this->interval;
	}

	public function should_run():bool
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
