<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;

class SchemaRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get_tables(string $schema):array
	{
		$tables = [];

        $rows = $this->db->fetchAllAssociative('select table_name from information_schema.tables
            where table_schema = ?
            order by table_name asc',
			[$schema],
			[\PDO::PARAM_STR]
		);

		foreach ($rows as $row)
		{
			$tables[$row['table_name']] = $row['table_name'];
		}

		return $tables;
	}
}
