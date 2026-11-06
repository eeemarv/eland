<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

class TransactionLogRepository
{
	public function __construct(
		private readonly Db $db,
	)
	{
	}

	public function insert(
		int $transaction_id,
    array $new_data,
    string|null $comment,
    int|null $created_by,
    string $route,
		Schema $schema,
    array|null $old_data = null,
    array|null $meta_data = null,
	):int
	{
    $old_data_ref = $old_data ?? [];
    $diff = array_diff_assoc($new_data, $old_data_ref);
    $diff_new = array_intersect_key($new_data, $diff);

    if (str_starts_with($route, 'transactions_'))
    {
      $action = substr($route, 13);
    }
    else
    {
      $action = $route;
    }

    $insert_ary = [
      'transaction_id' => $transaction_id,
      'route' => $route,
      'action'  => $action,
      'new_data'  => $diff_new,
      'comment' => $comment,
      'created_by'  => $created_by,
    ];

    if (isset($old_data))
    {
      $diff_old = array_intersect_key($old_data_ref, $diff);
      $insert_ary['old_data']  = $diff_old;
    }

    if (isset($meta_data))
    {
      $insert_ary['meta_data']  = $meta_data;
    }

    $type_ary = [
      'transaction_id' => Types::INTEGER,
      'route' => Types::STRING,
      'action'  => Types::STRING,
      'old_data'  => Types::JSONB,
      'new_data'  => Types::JSONB,
      'comment' => Types::STRING,
      'created_by'  => Types::INTEGER,
      'meta_data' => Types::JSONB,
    ];

		$affected_rows = (int) $this->db->insert(
      table: $schema->str() . '.transactions_logs',
      data: $insert_ary,
      types: $type_ary,
    );

    return $affected_rows;
	}

  public function bulk_insert(
		array $transactions_old_data_ary,
    array $new_data,
    string|null $comment,
    int|null $created_by,
    string $route,
    string $action,
		Schema $schema,
    array|null $meta_data = null,
	):int
	{
    $bulk_id = Uuid::v7();
    $rows = [];

    foreach ($transactions_old_data_ary as $transaction_id => $old_data)
    {
      $old_data_ref = $old_data ?? [];
      $diff = array_diff_assoc($new_data, $old_data_ref);
      $rows[] = [
        'transaction_id' => (int) $transaction_id,
        'old_data'  => array_intersect_key($old_data_ref, $diff),
        'new_data'  => array_intersect_key($new_data, $diff),
      ];
    }

    if (!isset($meta_data) || empty($meta_data))
    {
      $meta_data = (object) [];
    }

    $params = [
      'route' => $route,
      'action'  => $action,
      'comment' => $comment,
      'created_by'  => $created_by,
      'bulk_id' => $bulk_id->toRfc4122(),
      'meta_data' => $meta_data,
      'rows'  => $rows,
    ];

    $types = [
      'route' => Types::STRING,
      'action'  => Types::STRING,
      'comment' => Types::STRING,
      'created_by'  => Types::INTEGER,
      'bulk_id' => Types::GUID,
      'meta_data' => Types::JSONB,
      'rows'  => Types::JSONB,
    ];

    $sql = 'insert into ' . $schema->str() . '.transactions_logs
      (transaction_id, old_data, new_data, action, route,
        comment, created_by, bulk_id, meta_data)
      select (i->>\'transaction_id\')::int,
        i->\'old_data\',
        i->\'new_data\',
        :action, :route, :comment,
        :created_by, :bulk_id, :meta_data
      from jsonb_array_elements(:rows)
      as i';
    $affected_rows = (int) $this->db->executeStatement(
      $sql, $params, $types
    );

    return $affected_rows;
	}

  public function get_history(
    int $transaction_id,
    Schema $schema,
  ):array
  {
    $history_ary = [];
    $stmt = $this->db->prepare('select tl.*,
      jsonb_build_object(
        \'id\', cu.id,
        \'name\', cu.name,
        \'code\', cu.code,
        \'is_active\', cu.is_active,
        \'activated_at\', cu.activated_at,
        \'is_leaving\', cu.is_leaving,
        \'remote_schema\', cu.remote_schema,
        \'remote_email\', cu.remote_email
      ) as created_by_user
      from ' . $schema->str() . '.transactions_logs tl
      left join ' . $schema->str() . '.users cu
        on cu.id = tl.created_by
      where transaction_id = :transaction_id
      order by id desc');
    $stmt->bindValue('transaction_id', $transaction_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    while ($row = $res->fetchAssociative())
    {
      $created_by_user = json_decode($row['created_by_user'], true);
      $old_data = json_decode($row['old_data'], true);
      $new_data = json_decode($row['new_data'], true);
      $meta_data = json_decode($row['meta_data'], true);
      $history_ary[$row['id']] = [
        ...$row,
        'created_by_user'  => $created_by_user,
        'old_data'  => $old_data,
        'new_data'  => $new_data,
        'meta_data' => $meta_data,
      ];
    }
    return $history_ary;
  }
}
