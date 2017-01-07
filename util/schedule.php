<?php

namespace eland\util;

use Silex\Application;
use Symfony\Component\Finder\Finder;
use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\cache;
use eland\groups;
use eland\this_group;

class queue_task_schedule
{
	private $app;
	private $db;
	private $monolog;
	private $cache;
	private $groups;
	private $time;
	private $schema;
	private $name;
	private $event_time;
	private $this_group;
	private $cronjob_ary;

	private $next_ary = [];
	private $interval_ary = [];

	private $task_type;

	public function __construct(Application $app, db $db, Logger $monolog,
		cache $cache, groups $groups, this_group $this_group, string $task_type)
	{
		$this->app = $app;

		$this->db = $db;
		$this->monolog = $monolog;
		$this->cache = $cache;
		$this->groups = $groups;
		$this->this_group = $this_group;

		$this->cronjob_ary = $this->cache->get('cronjob_ary');

		$this->task_type = $task_type;

		$now = time();

		$finder = new Finder();
		$finder->files()
			->in(__DIR__ . '/../' . $this->task_type)
			->name('*.php');

		foreach ($finder as $file)
		{
			$path = $file->getRelativePathname();

			$task = basename($path, '.php');

			$this->interval_ary[$task] = $this->app['eland.' . $this->task_type . '.' . $task]->get_interval();
		}

		error_log('-- ' . $this->task_type . ': ');

		var_dump($this->interval_ary);

		foreach ($this->interval_ary as $task => $interval)
		{
			$this->next_ary[$task] = $now + $interval;
		}
	}

	public function process(array $cronjob_ary)
	{
		$r = "\n\r";

		$this->time = time();

		foreach ($this->tasks as $name => $t)
		{
			foreach ($this->groups->get_schemas() as $sch)
			{
				if (isset($this->cronjob_ary[$sch . '_cronjob_' . $name]))
				{
					if (isset($t[2]))
					{
						if (!readconfigfromdb($t[2], $sch))
						{
							continue;
						}
					}

					$multiply = (isset($t[1]) && $t[1]) ? readconfigfromdb($t[1], $sch) : 1;

					if (!$multiply)
					{
						continue;
					}

					$add = $t[0] * $multiply;

					$last_time = $this->cronjob_ary[$sch . '_cronjob_' . $name]['event_time'];

					$last = strtotime($last_time . ' UTC');

					$next = $last + $add;

					if ($next < $this->time)
					{
						$next = ((($this->time - $next) > 43200) || ($add < 43201)) ? $this->time : $next;
						$this->event_time = gmdate('Y-m-d H:i:s', $next);
						$this->schema = $sch;
						$this->name = $name;
						$this->this_group->force($sch);
						return true;
					}
				}
				else
				{
					$insert_schema = $sch;
					$insert_name = $name;
				}
			}
		}

		if (!isset($insert_schema))
		{
			return false;
		}

		if ($this->db->executeQuery('select tablename
			from pg_tables
			where schemaname = ? and tablename = \'cron\'', [$insert_schema]))
		{

			echo $insert_schema . '.cron table exists.' . $r;

			$event_time = $this->db->fetchColumn('select lastrun
				from ' . $insert_schema . '.cron
				where cronjob = ?', [$insert_name]);
		}

		if (isset($event_time))
		{
			echo 'move ' . $insert_schema . ' ' . $insert_name . ' to cache' . $r;
			$this->monolog->debug('move cronjob ' . $insert_name . ' to cache', ['schema' => $insert_schema]);
		}
		else
		{
			echo 'new ' . $insert_schema . ' ' . $insert_name . ' in cache' . $r;
			$event_time = gmdate('Y-m-d H:i:s', $this->time);
			$this->monolog->debug('new cronjob ' . $insert_name . ' in cache.', ['schema' => $insert_schema]);
		}

		$this->cronjob_ary[$insert_schema . '_cronjob_' . $insert_name]['event_time'] = $event_time;

		$this->cache->set('cronjob_ary', $this->cronjob_ary);

		return false;
	}

	public function get_schema()
	{
		return $this->schema;
	}

	public function get_name()
	{
		return $this->name;
	}

	public function update()
	{
		unset($this->cronjob_ary[$this->schema . '_cronjob_' . $this->name]);

		$this->cronjob_ary[$this->schema . '_cronjob_' . $this->name]['event_time'] = $this->event_time;

		$this->cache->set('cronjob_ary', $this->cronjob_ary);
	}
}
