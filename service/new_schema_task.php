<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\schedule;

// not used

class new_schema_task
{
	private $db;
	private $monolog;
	private $schedule;

	public function __construct(db $db, Logger $monolog, schedule $schedule)
	{
		$this->db = $db;
		$this->monolog = $monolog;
		$this->schedule = $schedule;
	}

	public function set(string $task, string $schema)
	{
		$r = "\r\n";

		if ($this->db->executeQuery('select tablename
			from pg_tables
			where schemaname = ? and tablename = \'cron\'', [$schema]))
		{

			$this->monolog->debug($schema . '.cron table exists.', ['schema' => $schema]);

			$event_time = $this->db->fetchColumn('select lastrun
				from ' . $insert_schema . '.cron
				where cronjob = ?', [$task]);
		}

		if (isset($event_time))
		{
			$this->schedule->set_time(strtotime($event_time . ' UTC'));
			$this->monolog->debug('move ' . $schema . '_' . $task . ' to cache', ['schema' => $schema]);
		}
		else
		{
			$this->schedule->set_time();
			$this->monolog->debug('new ' . $schema . ' ' . $task . ' in cache', ['schema' => $schema]);
		}

		$this->schedule->set_id($schema . '_' . $task)
			->set_interval()
			->update();
	}
}
