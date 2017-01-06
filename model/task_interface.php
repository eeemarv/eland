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
	 * should be called each cycle
	 */
	public function can_run()

	/*
	 * get the interval to the next task in seconds
	 * should be called at init
	 */
	public function get_interval()

	/*
	 * get the interval multiplicator according to the configuration
	 * should be called each cycle
	 */
	public function get_interval_multiplicator()
}
