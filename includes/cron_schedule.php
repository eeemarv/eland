<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\cache;
use eland\groups;
use eland\this_group;

class cron_schedule
{
	protected $db;
	protected $monolog;
	protected $cache;
	protected $groups;
	protected $time;
	protected $schema;
	protected $name;
	protected $event_time;
	protected $this_group;
	protected $cronjob_ary;

	protected $tasks = [
		'cleanup_cache'			=> [86400],
		'saldo'					=> [86400, 'saldofreqdays'],
		'user_exp_msgs'			=> [86400, '', 'msgexpwarnenabled'],
		'cleanup_messages'		=> [86400],
		'saldo_update'			=> [86400],
		'cleanup_news'			=> [86400],
		'cleanup_logs'			=> [86400],
		'cleanup_image_files'	=> [14400],
		'geocode' 				=> [7200],
		'interlets_fetch'		=> [7200],
	];

	public function __construct(db $db, Logger $monolog, cache $cache, groups $groups, this_group $this_group)
	{
		$this->db = $db;
		$this->monolog = $monolog;
		$this->cache = $cache;
		$this->groups = $groups;
		$this->this_group = $this_group;

		$this->cronjob_ary = $this->cache->get('cronjob_ary');
	}

	public function find_next()
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
