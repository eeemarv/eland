<?php

namespace eland\model;

use eland\model\task_interface;
use eland\model\task;

use eland\schedule;
use eland\groups;
use eland\this_group;

abstract class schema_task extends task implements task_interface
{
	private $groups;
	private $this_group;
	private $schema;

	public function __construct(schedule $schedule, groups $groups, this_group $this_group)
	{
		parent::__construct($schedule);

		$this->groups = $groups;
		$this->this_group = $this_group;
	}

	public function should_run()
	{
		$this->schedule->set_time();

		foreach ($this->groups->get_schemas() as $schema)
		{
			$this->schema = $schema;

			if (!$this->is_enabled())
			{
				continue;
			}

			$should_run = $this->schedule->set_id($schema . '_' . static::class)
				->set_interval($this->get_interval())
				->should_run();

			if ($should_run)
			{
				return true;
			}
		}

		return false;
	}

	public function run()
	{
		$this->this_group->force($this->schema);
		parent::run();
	}
}
