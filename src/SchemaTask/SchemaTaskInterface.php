<?php declare(strict_types=1);

namespace App\SchemaTask;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.schema_task')]
interface SchemaTaskInterface
{
	/**
	 * returns the default name to construct the id in the schema handler
	 */
	public static function get_default_index_name():string;

	/*
	 * run the task
	 */
	public function run(string $schema, bool $update):void;

	/*
	 *
	 */
	public function is_enabled(string $schema):bool;

	/*
	 * get the interval to the next task in seconds
	 * should be called each cycle
	 */
	public function get_interval(string $schema):int;
}
