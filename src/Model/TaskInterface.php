<?php declare(strict_types=1);

namespace App\Model;

interface TaskInterface
{
	/*
	 * if this task should run
	 */
	public function should_run():bool;

	/*
	 * run the task and updates schedule
	 */
	public function run():void;

	/*
	 *	called by ::run()
	 */
	public function process():void;

	/*
	 *
	 */
	public function is_enabled():bool;

	/*
	 * get the interval to the next task in seconds
	 * should be called each cycle
	 */
	public function get_interval():int;
}
