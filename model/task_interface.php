<?php

namespace eland\model;

interface task_interface
{
	/*
	 * run the task
	 */
	public function run()

	/*
	 * if this task can run according to configuration
	 */
	public function can_run()

	/*
	 * if this task should run now (time)
	 */
	public function should_run()

	/*
	 * get the interval to the next task in seconds
	 */
	public function get_interval()
}
