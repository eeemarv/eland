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
    mixed $new_data,
    mixed $old_data,
    string $route,
    int|null $user_id,
    Schema $schema,
    array|null $meta_data = null,
    string|null $comment = null,
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

    $this->db->beginTransaction();

    $this->db->executeStatement('update ' . $schema->str() . '.config
      set data = coalesce(:data::jsonb, \'null\'::jsonb),
      last_edit_by = :last_edit_by
      where id = :config_id', [
        'data' => $new_data,
        'last_edit_by'  => $user_id,
        'config_id'  => $config_id
      ], [
        'data'  => Types::JSON,
        'last_edit_by' => Types::INTEGER,
        'config_id'  => Types::STRING,
      ]);

    if (str_starts_with($route, 'config_'))
    {
      $action = substr($route, 7);
    }
    else
    {
      $action = $route;
    }

    $this->db->executeStatement('insert into ' .
      $schema->str() . '.config_logs
      (config_id, route, action,
      new_data, old_data, meta_data,
      created_by, comment)
      values
      (:config_id, :route, :action,
      coalesce(:new_data::jsonb, \'null\'::jsonb),
      coalesce(:old_data::jsonb, \'null\'::jsonb),
      coalesce(:meta_data::jsonb, \'{}\'::jsonb),
      :created_by, :comment)', [
        'config_id' => $config_id,
        'route' => $route,
        'action' => $action,
        'new_data'  => $new_data,
        'old_data'  => $old_data,
        'meta_data' => $meta_data,
        'created_by'  => $user_id,
        'comment' => $comment,
      ], [
        'config_id' => Types::STRING,
        'route' => Types::STRING,
        'action' => Types::STRING,
        'new_data'  => Types::JSON,
        'old_data'  => Types::JSON,
        'meta_data' => Types::JSON,
        'created_by'  => Types::INTEGER,
        'comment' => Types::STRING,
      ]);

    $this->db->commit();
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
