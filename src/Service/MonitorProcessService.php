<?php declare(strict_types=1);

namespace App\Service;

use Redis;
use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use App\Cnst\ProcessCnst;

class MonitorProcessService
{
	protected bool $is_cli;
	protected string $process_name;
	protected int $boot_count;
	protected int $loop_count = 1;

	public function __construct(
		protected Db $db,
		protected Redis $redis,
		protected CacheService $cache_service
	)
	{
		$this->is_cli = php_sapi_name() === 'cli';
	}

	public function boot(string $process_name):void
	{
		if (!$this->is_cli)
		{
			return;
		}

		$boot = $this->cache_service->get('boot');

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

		$this->cache_service->set('boot', $boot);

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
		$monitor = $this->redis->get('monitor_processes');

		if (is_string($monitor))
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

		$this->redis->set('monitor_processes', json_encode($monitor), 86400);

		sleep(ProcessCnst::INTERVAL[$this->process_name]['wait']);

		return true;
	}

	public function periodic_log():void
	{
		if ($this->loop_count
			% ProcessCnst::INTERVAL[$this->process_name]['log']
			=== 0)
		{
			error_log('.. ' . $this->process_name . ' .. ' .
				$this->boot_count .
				' .. ' .
				$this->loop_count);
		}

		$this->loop_count++;
	}

	public function get_loop_count():int
	{
		return $this->loop_count;
	}

	public function monitor()
	{
		try
		{
			$this->db->fetchOne('select schema_name
				from information_schema.schemata', [], []);
		}
		catch(\Exception $e)
		{
			error_log('db_fail: ' . $e->getMessage());
			throw $e;
			exit;
		}

		try
		{
			$this->redis->incr('eland_monitor');
			$this->redis->expire('eland_monitor', 300);
			$monitor_count = $this->redis->get('eland_monitor');

			if ($monitor_count > 2)
			{
				$monitor_processes = $this->redis->get('monitor_processes');

				if (!$monitor_processes)
				{
					http_response_code(503);
					echo 'processes are down';
					exit;
				}

				error_log('monitor_processes: ' . $monitor_processes);

				$monitor_processes = json_decode($monitor_processes, true);
				$now = time();

				foreach (ProcessCnst::INTERVAL as $process_name => $process_interval)
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
