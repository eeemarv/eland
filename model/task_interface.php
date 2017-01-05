<?php

namespace eland\model;

interface task_interface
{
	public function needs_schema()

	public function set_schema(string $schema)

	public function get_schema()

	public function run()

	/*
	 * if this task can run according to configuration
	 */
	public function can_run()

	/*
	 * if this task should run now (time)
	 */
	public function should_run()

	public function get_interval()
}
