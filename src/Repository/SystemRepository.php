<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;

class SystemRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get_schema_ary():array
	{
		$schema_ary = [];

		/**
		 * xdb.t_remote_schema is parent table
		 * to users tables with columns remote_schema
		 * and is_active.
		 */
		$stmt = $this->db->prepare('select s.schema_name,
				t.remote_schema, t.is_active
			from information_schema.schemata s
			left join xdb.t_remote_schema t
				on t.remote_schema is not null
				and s.schema_name = split_part(t.tableoid::regclass::text, \'.\', 1)
			where not starts_with(s.schema_name, \'pg_\')
			and not starts_with(s.schema_name, \'eland_\')
			and schema_name not in (\'xdb\', \'c\', \'e\',
				\'public\', \'template\', \'temp\',
				\'migration\', \'information_schema\')');

		$res = $stmt->executeQuery();

		while($row = $res->fetchAssociative())
		{
			$schema = $row['schema_name'];

			if (!isset($schema_ary[$schema]))
			{
				$schema_ary[$schema] = [];
			}

			if (isset($row['remote_schema']))
			{
				$schema_ary[$schema][$row['remote_schema']] = $row['is_active'];
			}
		}

		return $schema_ary;
	}
}
