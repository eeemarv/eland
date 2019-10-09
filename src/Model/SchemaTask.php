<?php declare(strict_types=1);

namespace App\Model;

use App\Model\TaskInterface;
use App\Model\Task;

use App\Service\Schedule;
use App\Service\Systems;

abstract class SchemaTask extends Task implements TaskInterface
{
	protected $systems;
	protected $schema;

	public function __construct(
		Schedule $schedule,
		Systems $systems
	)
	{
		parent::__construct($schedule);

		$this->systems = $systems;
	}

	public function should_run():bool
	{
		$this->schedule->set_time();

		foreach ($this->systems->get_schemas() as $schema)
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

	/**
	 * Only for manual testing (periodic overview)
	 */
	public function set_schema(string $schema):void
	{
		$this->schema = $schema;
	}
}
