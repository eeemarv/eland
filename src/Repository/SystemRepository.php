<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;

class SystemRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

	public function get_schema_ary():array
	{
		$schema_ary = [];

		/**
		 * xdb.p_users is parent table
		 * to users tables with columns remote_schema,
		 * is_active and status.
		 */
		$stmt = $this->db->prepare('select s.schema_name,
				pu.remote_schema, pu.is_active, pu.status
			from information_schema.schemata s
			left join xdb.p_users pu
				on pu.remote_schema is not null
				and s.schema_name = split_part(pu.tableoid::regclass::text, \'.\', 1)
			where not starts_with(s.schema_name, \'pg_\')
			and not starts_with(s.schema_name, \'eland_\')
			and s.schema_name not in (\'xdb\', \'c\', \'e\',
				\'public\', \'template\', \'temp\',
				\'migration\', \'information_schema\')
      order by s.schema_name asc, pu.remote_schema asc');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$schema = $row['schema_name'];

			if (!isset($schema_ary[$schema]))
			{
				$schema_ary[$schema] = [];
			}

			if (isset($row['remote_schema']))
			{
				// later $row['is_active'] is to be used
				$is_active = in_array($row['status'], [1, 2, 7], true);
				$schema_ary[$schema][$row['remote_schema']] = $is_active;
			}
		}

		return $schema_ary;
	}
}
