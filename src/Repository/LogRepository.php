<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class LogRepository
{
	public function __construct(
		private readonly Db $db,
	)
	{
	}

  public function get_types(
    Schema $schema,
  ):array
  {
    $types = [];
    $stmt = $this->db->prepare('select distinct type
      from xdb.logs
      where schema = :schema
      order by type asc');
    $stmt->bindValue('schema', $schema->str(), Types::STRING);
    $res = $stmt->executeQuery();
    while ($row = $res->fetchAssociative())
    {
      $types[] = $row['type'];
    }
    return $types;
  }

  public function get_filtered(
    int|null $user_id,
    string|null $type,
    string|null $event,
    \DateTimeImmutable|null $from_date,
    \DateTimeImmutable|null $to_date,
    int $start,
    int $limit,
    string $order_by,
    bool $asc,
    array $inter_ary, // connected intersystems
    Schema $schema,
  ):array
  {
    $schema_str = $schema->str();

    $sql = [];
    $sql['where']['schema'] = 'schema = :schema';
    $sql['params']['schema'] = $schema_str;
    $sql['types']['schema'] = Types::STRING;

    if (isset($user_id))
    {
      $sql['where']['user_id'] = 'user_id = :user_id';
      $sql['params']['user_id'] = $user_id;
      $sql['types']['user_id'] = Types::INTEGER;
      $sql['where']['user_schema'] = 'user_schema = :user_schema';     
      $sql['params']['user_schema'] = $schema_str;
      $sql['types']['user_schema'] = TYPES::STRING;
    }

    if (isset($type))
    {
      $sql['where']['type'] = 'type ilike :type';
      $sql['params']['type'] = strtolower($type);
      $sql['types']['type'] = Types::STRING;
    }

    if (isset($event))
    {
      $sql['where']['event'] = 'event ilike :event';
      $sql['params']['event'] = '%' . $event . '%';
      $sql['types']['event'] = Types::STRING;
    }

    if (isset($from_date))
    {
      $sql['where']['from_date'] = 'ts >= :from_date';
      $sql['params']['from_date'] = $from_date;
      $sql['types']['from_date'] = Types::DATETIME_IMMUTABLE;
    }

    if (isset($to_date))
    {
      $sql['where']['to_date'] = 'ts <= :to_date';
      $sql['params']['to_date'] = $to_date;
      $sql['types']['to_date'] = Types::DATETIME_IMMUTABLE;
    }

    $sql['params']['limit'] = $limit;
    $sql['types']['limit'] = Types::INTEGER;
    $sql['params']['offset'] = $start;
    $sql['types']['offset'] = Types::INTEGER;

    $sql_where = implode(' and ', $sql['where']);

    $query = 'select l.*,
      u.code, u.name, u.is_active, u.is_leaving,
      u.activated_at, u.remote_email, u.remote_schema
      from xdb.logs l
      left join ' . $schema_str . '.users u
        on (u.id = l.user_id and l.user_schema = :schema)
      where ' . $sql_where;
    $query .= ' order by ' . $order_by . ' ';
    $query .= $asc ? 'asc ' : 'desc ';
    $query .= 'limit :limit offset :offset';

    $res = $this->db->executeQuery($query,
      $sql['params'],
      $sql['types']);

    $logs = [];
    $remote_ary = [];

    while ($row = $res->fetchAssociative())
    {
      $logs[] = $row;

      if (!isset($row['user_schema']))
      {
        continue;
      }

      if ($schema_str === $row['user_schema'])
      {
        continue;
      }

      if (!isset($inter_ary[$row['user_schema']]))
      {
        // system/schema is not connected
        continue;
      }

      if (!isset($remote_ary[$row['user_schema']]))
      {
        $remote_ary[$row['user_schema']] = [];
      }

      $remote_ary[$row['user_schema']][] = $row['user_id'];
    }

    $sql_all = $sql;
    unset($sql_all['params']['limit']);
    unset($sql_all['types']['limit']);
    unset($sql_all['params']['offset']);
    unset($sql_all['types']['offset']);

    $sql_all_where = implode(' and ', $sql_all['where']);

    $row_count = $this->db->fetchOne('select count(*)
      from xdb.logs
      where ' . $sql_all_where,
      $sql_all['params'], 
      $sql_all['types'],
    );

    $remote_user_ary = [];

    foreach ($remote_ary as $r_schema => $user_ids)
    {
      $query = 'select u.id, u.code, u.name
        from ' . $r_schema . '.users u
        where u.id in (:user_ids)
          and u.is_active 
          and remote_email is null
          and remote_schema is null';
      $res = $this->db->executeQuery($query, [
        'user_ids'  => $user_ids,
      ], [
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
      while ($row = $res->fetchAssociative())
      {
        if (!isset($remote_user_ary[$r_schema]))
        {
          $remote_user_ary[$r_schema] = [];
        }
        $remote_user_ary[$r_schema][$row['id']] = $row;
      }
    }

    return [
      'logs' => $logs,
      'row_count' => $row_count,
      'remote_user_ary' => $remote_user_ary,
    ];
  }
}
