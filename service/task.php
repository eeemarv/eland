<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\schedule;
use eland\groups;
use eland\this_group;

class task
{
	private $db;
	private $monolog;
	private $schedule;
	private $groups;
	private $time;
	private $schema;
	private $name;
	private $this_group;

	private $tasks = [
		'cleanup_cache'			=> [86400],
		'saldo'					=> [86400, 'saldofreqdays'],
		'user_exp_msgs'			=> [86400, '', 'msgexpwarnenabled'],
		'cleanup_messages'		=> [86400],
		'saldo_update'			=> [86400],
		'cleanup_news'			=> [86400],
		'cleanup_logs'			=> [86400],
		'cleanup_image_files'	=> [900],
		'geocode' 				=> [900],
		'interlets_fetch'		=> [900],
	];

	public function __construct(db $db, Logger $monolog, schedule $schedule, groups $groups, this_group $this_group)
	{
		$this->db = $db;
		$this->monolog = $monolog;
		$this->schedule = $schedule;
		$this->groups = $groups;
		$this->this_group = $this_group;
	}

	public function find_next()
	{
		$r = "\n\r";

		$this->schedule->set_time();

		foreach ($this->tasks as $name => $t)
		{
			foreach ($this->groups->get_schemas() as $sch)
			{
				$this->schedule->set_id($sch . '_' . $name);

				if ($this->schedule->exists())
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

					if ($this->schedule->should_run($add))
					{
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

		$this->schedule->set_id($insert_schema . '_' . $insert_name)
			->set_time(strtotime($event_time . ' UTC'));
			->set_interval(0)
			->update();

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
		$this->schedule->update();
	}
}
