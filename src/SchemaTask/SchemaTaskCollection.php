<?php declare(strict_types=1);

namespace App\SchemaTask;

class SchemaTaskCollection
{
	protected $schema_tasks;

	public function __construct(
		iterable $schema_tasks
	)
	{
		$this->schema_tasks = iterator_to_array($schema_tasks);
	}

	public function get_index_names():array
	{
		return array_keys($this->schema_tasks);
	}

	public function get(string $index_name):SchemaTaskInterface
	{
		return $this->schema_tasks[$index_name];
	}
}
