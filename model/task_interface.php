<?php

namespace eland\model;

interface task_interface
{
	/*
	 * if this task should run 
	 */
	public function should_run();

	/*
	 * run the task and updates schedule
	 */
	public function run();

	/*
	 *	called by ::run()
	 */
	public function process();

	/*
	 *
	 */
	public function is_enabled();

	/*
	 * get the interval to the next task in seconds
	 * should be called each cycle
	 */
	public function get_interval();
}
