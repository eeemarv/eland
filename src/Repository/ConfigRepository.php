<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use LogicException;

class ConfigRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

	public function set_value(
    string $config_id,
    mixed $value,
    int|null $user_id,
    Schema $schema,
  ):void
	{
		$path_ary = explode('.', $config_id);

		foreach($path_ary as $p)
		{
			if (!preg_match('/^[a-z_]+$/', $p))
			{
				throw new LogicException('Unacceptable format config id');
			}
		}

		if (isset($value))
		{
			$this->db->executeStatement('update ' . $schema->str() . '.config
				set data = :data, last_edit_by = :last_edit_by
				where id = :config_id', [
          'data' => $value,
          'last_edit_by'  => $user_id,
          'config_id'  => $config_id
        ], [
          'data'  => Types::JSON,
          'last_edit_by' => Types::INTEGER,
          'config_id'  => Types::STRING,
        ]);
		}
		else
		{
			$this->db->executeStatement('update ' . $schema->str() . '.config
				set data = \'null\'::jsonb, last_edit_by = :last_edit_by
				where id = :config_id', [
          'last_edit_by'  => $user_id,
          'config_id' => $config_id,
        ], [
          'last_edit_by'  => Types::INTEGER,
          'config_id' => Types::STRING,
        ]
			);
		}
	}

	public function get_all(
    Schema $schema,
  ):array
	{
		$ary = [];

		$stmt = $this->db->prepare('select id, data
			from ' . $schema->str() . '.config');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$ary[$row['id']] = json_decode($row['data'], true);
		}

		return $ary;
	}
}
