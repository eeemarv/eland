<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\xdb;
use eland\groups;

class cron_schedule
{
	protected $db;
	protected $monolog;
	protected $xdb;
	protected $groups;
	protected $time;
	protected $sha;
	protected $schema;
	protected $name;
	protected $event_time;

	protected $tasks = [
		'saldo'					=> [86400, 'saldofreqdays'],
		'admin_exp_msg'			=> [86400, 'adminmsgexpfreqdays', 'adminmsgexp'],
		'user_exp_msgs'			=> [86400, '', 'msgexpwarnenabled'],
		'cleanup_messages'		=> [86400],
		'saldo_update'			=> [86400],
		'cleanup_news'			=> [86400],
		'cleanup_logs'			=> [86400],
		'geocode' 				=> [7200],
		'cleanup_image_files'	=> [3600],
	];

	public function __construct(db $db, Logger $monolog, xdb $xdb, groups $groups)
	{
		$this->db = $db;
		$this->monolog = $monolog;
		$this->xdb = $xdb;
		$this->groups = $groups;
		$this->time = time();
		$this->sha = sha1($this->time());
	}

	public function search_next()
	{
		$cronjob_ary = $this->xdb->get_many(['agg_type' => 'cronjob']);

		foreach ($this->tasks as $name => $t)
		{
			foreach ($this->groups->get_schemas() as $sch)
			{
				if (isset($cronjob_ary[$sch . '_cronjob_' . $name]))
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

					$cronjob = $cronjob_ary[$sch . '_cronjob_' . $name];

					$last = strtotime($cronjob['event_time'] . ' UTC');

					$next = $last + $add;

					if ($next < $this->time())
					{
						$next = ((($this->time - $next) > 43200) || ($add < 43201)) ? $this->time : $next;
						$this->event_time = gmdate('Y-m-d H:i:s', $next);
						$this->schema = $sch;
						$this->name = $name;
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

		$schema_manager = $this->db->getSchemaManager();

		if ($schema_manager->tablesExist([$insert_schema . '.cron']))
		{
			$event_time = $this->db->fetchColumn('select lastrun
				from ' . $insert_schema . '.cron
				where cronjob = ?', [$insert_name]);
		}

		if ($event_time)
		{
			$this->monolog->debug('move cronjob ' . $insert_name . ' to xdb', ['schema' => $insert_schema]);
		}
		else
		{
			$event_time = gmdate('Y-m-d H:i:s', $this->time());			
			$this->monolog->debug('new cronjob ' . $insert_name . ' in xdb.', ['schema' => $insert_schema]);
		}

		$this->xdb->set('cronjob', $insert_name, ['sha' => $this->sha], $insert_schema, $event_time);

		return false;
	}

	public function get_schema()
	{
		return $this->schema;
	}

	public function get_name();
	{
		return $this->name;
	}

	public function update()
	{
		$this->xdb->set('cronjob', $this->name, ['sha' => $this->sha], $this->schema, $this->event_time);
	}
}