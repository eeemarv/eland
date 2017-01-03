<?php

namespace eland\model;

abstract class task
{
	protected $schema = '';

	function needs_schema()
	{
		return true;
	}

	function set_schema($schema)
	{
		$this->schema = $schema;
	}

	function get_schema()
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
