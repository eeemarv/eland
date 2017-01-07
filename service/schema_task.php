<?php

namespace eland;

use eland\schedule;
use eland\new_schema_task;
use eland\groups;
use eland\this_group;

class schema_task
{
	private $schedule;
	private $new_schema_task;
	private $groups;
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

	public function __construct(schedule $schedule, new_schema_task $new_schema_task, groups $groups, this_group $this_group)
	{
		$this->schedule = $schedule;
		$this->new_schema_task = $new_schema_task;
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

		$this->new_schema_task->set($insert_name, $insert_schema);

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
