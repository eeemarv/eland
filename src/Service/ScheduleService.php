<?php declare(strict_types=1);

namespace App\Service;

use App\Service\CacheService;

class ScheduleService
{
	const MINIMUM_INTERVAL = 720;
	const CACHE_KEY = 'tasks';

	protected $cache_service;

	protected $tasks;
	protected $time;
	protected $next;
	protected $interval;
	protected $id;

	public function __construct(
		CacheService $cache_service
	)
	{
		$this->cache_service = $cache_service;

		$this->tasks = $this->cache_service->get(self::CACHE_KEY);
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
			error_log('insert task: ' . $this->id);

			$this->tasks[$this->id] = gmdate('Y-m-d H:i:s', $this->time + mt_rand(60, 900));

			$this->cache_service->set(self::CACHE_KEY, $this->tasks);

			return false;
		}

		$last = strtotime($this->tasks[$this->id] . ' UTC');

		if (($this->time - $last) < self::MINIMUM_INTERVAL)
		{
			return false;
		}

		$this->next = $last + $this->interval;

		if ($this->next >= $this->time)
		{
			return false;
		}

		$next = ((($this->time - $this->next) > 43200) || ($this->interval < 43201)) ? $this->time : $this->next;

		$next = gmdate('Y-m-d H:i:s', $next);

		$this->tasks[$this->id] = $next;

		$this->cache_service->set(self::CACHE_KEY, $this->tasks);

		error_log('update & run: ' . $this->id);

		return true;
	}
}
