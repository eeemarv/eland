<?php declare(strict_types=1);

namespace App\SchemaTask;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class SchemaTaskCollection
{
	protected $schema_tasks = [];

	public function __construct(
		#[TaggedIterator('app.schema_task')] iterable $schema_tasks
	)
	{
		// The keys aren't set as expected so we retrieve it from the static method

		foreach ($schema_tasks as $schema_task)
		{
			$this->schema_tasks[$schema_task::get_default_index_name()] = $schema_task;
		}
	}

	public function get_schema_task_names():array
	{
		return array_keys($this->schema_tasks);
	}

	public function get(string $index_name):SchemaTaskInterface
	{
		return $this->schema_tasks[$index_name];
	}
}
