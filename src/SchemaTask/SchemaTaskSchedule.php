<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Cache\SystemsCache;
use App\Service\CacheService;

class SchemaTaskSchedule
{
	const MINIMUM_INTERVAL = 3600;
	const CACHE_KEY = 'tasks';

	protected array $last_run_ary;

	public function __construct(
		protected CacheService $cache_service,
		protected SystemsCache $systems_cache,
		protected SchemaTaskCollection $schema_task_collection
	)
	{
		$this->last_run_ary = $this->cache_service->get(self::CACHE_KEY);
	}

	public function get_schema_task_names():array
	{
		return $this->schema_task_collection->get_schema_task_names();
	}

	public function get_last_run_ary():array
	{
		return $this->last_run_ary;
	}

	public function get_id(string $schema, string $schema_task_name):string
	{
		return $schema . '_' . $schema_task_name;
	}

	public function process():void
	{
		$next_run_check = [];
		$id_info = [];
		$time = time();
		$schema_task_names = $this->get_schema_task_names();
        $schemas = array_keys($this->systems_cache->get_schema_ary());

		foreach ($schema_task_names as $schema_task_name)
		{
			foreach($schemas as $schema)
			{
				$schema_task = $this->schema_task_collection->get($schema_task_name);

				if (!$schema_task->is_enabled($schema))
				{
					continue;
				}

				$id = $this->get_id($schema, $schema_task_name);

				if (!isset($this->last_run_ary[$id]))
				{
					$this->last_run_ary[$id] = gmdate('Y-m-d H:i:s', $time + mt_rand(60, 900));
					$this->cache_service->set(self::CACHE_KEY, $this->last_run_ary);
					return;
				}

				$last = strtotime($this->last_run_ary[$id] . ' UTC');

				if (($time - $last) < self::MINIMUM_INTERVAL)
				{
					continue;
				}

				$interval = $schema_task->get_interval($schema);
				$next_run_check[$id] = $last + $interval;

				$id_info[$id] = [
					'schema' 			=> $schema,
					'schema_task'		=> $schema_task,
					'interval'			=> $interval,
				];
			}
		}

		if (!count($next_run_check))
		{
			return;
		}

		asort($next_run_check);
		$id = array_key_first($next_run_check);

		$schema = $id_info[$id]['schema'];
		$schema_task = $id_info[$id]['schema_task'];
		$interval = $id_info[$id]['interval'];

		$next_time = $next_run_check[$id];

		if ($next_time > $time)
		{
			return;
		}

		$time_register = ((($time - $next_time) > 43200) || ($interval < 43201)) ? $time : $next_time;
		$this->last_run_ary[$id] = gmdate('Y-m-d H:i:s', $time_register);
		$this->cache_service->set(self::CACHE_KEY, $this->last_run_ary);

		error_log('update & run: ' . $id);

		$schema_task->run($schema, true);
	}
}
