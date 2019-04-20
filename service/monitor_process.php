<?php

namespace service;

use Doctrine\DBAL\Connection as db;
use Predis\Client as predis;
use service\cache;
use util\cnst;

class monitor_process
{
	protected $db;
	protected $predis;
	protected $cache;
	protected $ttl = 2592000;
	protected $is_cli;
	protected $process_name;
	protected $boot_count;
	protected $loop_count = 1;

	public function __construct(
		db $db,
		predis $predis,
		cache $cache
	)
	{
		$this->db = $db;
		$this->predis = $predis;
		$this->cache = $cache;

		$this->is_cli = php_sapi_name() === 'cli';
	}

	public function boot():void
	{
		if (!$this->is_cli)
		{
			return;
		}

		$process_name = basename($_SERVER['SCRIPT_FILENAME'], '.php');

		$boot = $this->cache->get('boot');

		if (!count($boot))
		{
			$boot['count'] = 0;
		}

		if (!isset($boot[$process_name]))
		{
			$boot[$process_name] = $boot['count'];
		}

		$boot[$process_name]++;

		$this->boot_count = $boot[$process_name];

		$this->cache->set('boot', $boot);

		error_log('.. ' . $process_name . ' started .. ' . $this->boot_count);

		$this->process_name = $process_name;
	}

	public function wait_most_recent():bool
	{
		if (!$this->is_cli)
		{
			return false;
		}

		$now = time();
		$monitor = $this->predis->get('monitor_processes');

		if (isset($monitor))
		{
			$monitor = json_decode($monitor, true);
		}
		else
		{
			$monitor = [];
		}

		$monitor[$this->process_name][$this->boot_count] = $now;

		$process_ary = $monitor[$this->process_name];

		if (max(array_keys($process_ary)) !== $this->boot_count)
		{
			sleep(300);
			return false;
		}

		$day_ago = $now - 86400;

		foreach ($process_ary as $count => $time)
		{
			if ($time < $day_ago)
			{
				unset($process_ary[$count]);
			}
		}

		$monitor[$this->process_name] = $process_ary;

		$this->predis->set('monitor_processes', json_encode($monitor));
		$this->predis->expire('monitor_processes', 86400);

		sleep(cnst::PROCESS_INTERVAL[$this->process_name]['wait']);

		return true;
	}

	public function periodic_log():void
	{
		if ($this->loop_count
			% cnst::PROCESS_INTERVAL[$this->process_name]['log']
			=== 0)
		{
			error_log('.. ' . $this->process_name . ' .. ' .
				$this->boot_count .
				' .. ' .
				$this->loop_count);
		}

		$this->loop_count++;
	}

	public function monitor()
	{
		try
		{
			$this->db->fetchColumn('select schema_name
				from information_schema.schemata');
		}
		catch(\Exception $e)
		{
			error_log('db_fail: ' . $e->getMessage());
			throw $e;
			exit;
		}

		try
		{
			$this->predis->incr('eland_monitor');
			$this->predis->expire('eland_monitor', 300);
			$monitor_count = $this->predis->get('eland_monitor');

			if ($monitor_count > 2)
			{
				$monitor_processes = $this->predis->get('monitor_processes');

				if (!$monitor_processes)
				{
					http_response_code(503);
					echo 'processes are down';
					exit;
				}

				error_log('monitor_processes: ' . $monitor_processes);

				$monitor_processes = json_decode($monitor_processes, true);

				foreach (cnst::PROCESS_INTERVAL as $process_name => $process_interval)
				{
					if (!isset($monitor_processes[$process_name]))
					{
						http_response_code(503);
						error_log('no time found for process: ' . $process_name);
						exit;
					}

					$process_ary = $monitor_processes[$process_name];
					$active = max(array_keys($process_ary));
					$last = $process_ary[$active];

					$process_monitor = $process_interval['monitor'];
					$now = now();

					if (($last + $process_monitor) < $now)
					{
						http_response_code(503);
						echo ('Process down: ' . $process_name .
							', max interval: ' . $process_monitor .
							', last time: ' . $last .
							', now: ' . $now);
						exit;
					}

				}

				echo 'Ok.';
				exit;
			}
		}
		catch(\Exception $e)
		{
			error_log('redis_fail: ' . $e->getMessage());
			throw $e;
			exit;
		}
	}
}
