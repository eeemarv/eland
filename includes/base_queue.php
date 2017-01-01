<?php

namespace eland;

abstract class base_queue //implements \eland\task
{
	private $name = '';
	private $schema = '';

	public function run()
	{
	}

	public function get_name()
	{
		return $name;
	}

	public function set_name(string $name)
	{
		$this->name = $name;
	}

	public function get_schema()
	{
		return $this->schema;
	}

	public function set_schema($schema)
	{
		$this->schema = $schema;
	}

	public function has_schema()
	{
		return true;
	}

	public function can_run()
	{
		return true;
	}

	public function should_run()
	{
		return true;
	}
}
