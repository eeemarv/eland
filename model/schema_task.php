<?php

namespace model;

use model\task_interface;
use model\task;

use service\schedule;
use service\groups;

abstract class schema_task extends task implements task_interface
{
	protected $groups;
	protected $schema;

	public function __construct(
		schedule $schedule,
		groups $groups
	)
	{
		parent::__construct($schedule);

		$this->groups = $groups;
	}

	public function should_run():bool
	{
		$this->schedule->set_time();

		foreach ($this->groups->get_schemas() as $schema)
		{
			$this->schema = $schema;

			if (!$this->is_enabled())
			{
				continue;
			}

			$should_run = $this->schedule->set_id($this->schema . '_' . $this->get_class_name())
				->set_interval($this->get_interval())
				->should_run();

			if ($should_run)
			{
				return true;
			}
		}

		return false;
	}
}
