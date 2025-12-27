<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class SchemaRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

	public function get_tables(
    Schema $schema,
  ):array
	{
		$tables = [];

    $rows = $this->db->fetchAllAssociative('select table_name from information_schema.tables
      where table_schema = :schema
      order by table_name asc', [
      'schema'  => $schema->str(),
    ], [
      'schema' => Types::STRING,
    ]);

		foreach ($rows as $row)
		{
			$tables[$row['table_name']] = $row['table_name'];
		}

		return $tables;
	}
}
