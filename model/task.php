<?php

namespace eland\model;

use eland\model\task_interface;

abstract class task implements task_interface
{
	private $schema = '';

	public function needs_schema()
	{
		return true;
	}

	public function set_schema($schema)
	{
		$this->schema = $schema;
	}

	public function get_schema()
	{
		return $this->schema;
	}

	public function run()
	{
	}

	public function can_run()
	{
		return true;
	}

	public function should_run()
	{
		return true;
	}

	public function get_interval()
	{
		return 86400;
	}
}
