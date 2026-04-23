<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class AccountRepository
{
	public function __construct(
    private readonly Db $db
  )
	{
	}

  public function get_min_limit(
    int $account_id,
    Schema $schema
  ):int|null
  {
    $stmt = $this->db->prepare('select m.min_limit
      from (values(0)) as d
      left join ' . $schema->str() . '.min_limit as m
      on m.account_id = :account_id
      order by m.id desc
      limit 1');
    $stmt->bindValue('account_id', $account_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    return $res->fetchOne();
  }

  public function set_min_limit(
    int $account_id,
    int|null $min_limit,
    int|null $created_by,
    Schema $schema
  ):void
  {
    $cols = ['account_id'];
    if (isset($min_limit))
    {
      $cols[] = 'min_limit';
    }
    if (isset($created_by))
    {
      $cols[] = 'created_by';
    }
    $stmt = $this->db->prepare('insert into ' .
      $schema->str() . '.min_limit
      (' . implode(', ', $cols) . ')
      values
      (:' . implode(', :', $cols) . ')');
    $stmt->bindValue('account_id', $account_id, Types::INTEGER);
    if (isset($min_limit))
    {
      $stmt->bindValue('min_limit', $min_limit, Types::INTEGER);
    }
    if (isset($created_by))
    {
      $stmt->bindValue('created_by', $created_by, Types::INTEGER);
    }
    $stmt->executeStatement();
  }

	public function set_bulk_min_limit(
		array $account_ids,
    int|null $min_limit,
    int|null $created_by,
		Schema $schema,
	):int
	{
    $a_ids_str = '{';
    $a_ids_str .= implode(',', $account_ids);
    $a_ids_str .= '}';

    $params = [
      'min_limit' => $min_limit,
      'created_by'  => $created_by,
      'a_ids_str' => $a_ids_str,
    ];

    $types = [
      'min_limit' => Types::INTEGER,
      'created_by'  => Types::INTEGER,
      'a_ids_str' => Types::STRING,
    ];

    $sql = 'insert into ' . $schema->str() . '.min_limit
      (min_limit, created_by, account_id)
      select :min_limit, :created_by,
        unnest(:a_ids_str::int[])';

    $affected_rows = (int) $this->db->executeStatement(
      $sql, $params, $types
    );

    return $affected_rows;
	}

  public function get_min_limit_ary(
    Schema $schema
  ):array
  {
    $min_limit_ary = [];

    $stmt = $this->db->prepare('select distinct on(account_id) min_limit, account_id
      from ' . $schema->str() . '.min_limit
      order by account_id, id desc');
    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $min_limit_ary[$row['account_id']] = $row['min_limit'];
    }

    return $min_limit_ary;
  }

  public function get_max_limit(
    int $account_id,
    Schema $schema
  ):int|null
  {
    $stmt = $this->db->prepare('select m.max_limit
      from (values(0)) as d
      left join ' . $schema->str() . '.max_limit as m
      on m.account_id = :account_id
      order by m.id desc
      limit 1');
    $stmt->bindValue('account_id', $account_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    return $res->fetchOne();
  }

  public function set_max_limit(
    int $account_id,
    int|null $max_limit,
    int|null $created_by,
    Schema $schema
  ):void
  {
    $cols = ['account_id'];
    if (isset($max_limit))
    {
      $cols[] = 'max_limit';
    }
    if (isset($created_by))
    {
      $cols[] = 'created_by';
    }
    $stmt = $this->db->prepare('insert into ' .
      $schema->str() . '.max_limit
      (' . implode(', ', $cols) . ')
      values
      (:' . implode(', :', $cols) . ')');
    $stmt->bindValue('account_id', $account_id, Types::INTEGER);
    if (isset($max_limit))
    {
      $stmt->bindValue('max_limit', $max_limit, Types::INTEGER);
    }
    if (isset($created_by))
    {
      $stmt->bindValue('created_by', $created_by, Types::INTEGER);
    }
    $stmt->executeStatement();
  }

	public function set_bulk_max_limit(
		array $account_ids,
    int|null $max_limit,
    int|null $created_by,
		Schema $schema,
	):int
	{
    $a_ids_str = '{';
    $a_ids_str .= implode(',', $account_ids);
    $a_ids_str .= '}';

    $params = [
      'max_limit' => $max_limit,
      'created_by'  => $created_by,
      'a_ids_str' => $a_ids_str,
    ];

    $types = [
      'max_limit' => Types::INTEGER,
      'created_by'  => Types::INTEGER,
      'a_ids_str' => Types::STRING,
    ];

    $sql = 'insert into ' . $schema->str() . '.max_limit
      (max_limit, created_by, account_id)
      select :max_limit, :created_by,
        unnest(:a_ids_str::int[])';

    $affected_rows = (int) $this->db->executeStatement(
      $sql, $params, $types
    );

    return $affected_rows;
	}

  public function get_max_limit_ary(
    Schema $schema,
  ):array
  {
    $max_limit_ary = [];

    $stmt = $this->db->prepare('select distinct on(account_id) max_limit, account_id
      from ' . $schema->str() . '.max_limit
      order by account_id, id desc');
    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $max_limit_ary[$row['account_id']] = $row['max_limit'];
    }

    return $max_limit_ary;
  }

  public function get_balance(
    int $account_id,
    Schema $schema,
  ):int
  {
    $stmt = $this->db->prepare('select coalesce(b.balance, 0)
      from (values(0)) as d
      left join ' . $schema->str() . '.balance as b
      on b.account_id = :account_id
      order by b.id desc
      limit 1');
    $stmt->bindValue('account_id', $account_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    return $res->fetchOne();
  }

  public function get_balance_on_date(
    int $account_id,
    \DateTimeImmutable $datetime,
    Schema $schema
  ):int
  {
    $stmt = $this->db->prepare('select coalesce(b.balance, 0)
      from (values(0)) as d
      left join ' . $schema->str() . '.balance as b
      on b.account_id = :account_id and b.created_at <= :datetime
      order by b.id desc
      limit 1');
    $stmt->bindValue('account_id', $account_id, Types::INTEGER);
    $stmt->bindValue('datetime', $datetime, Types::DATETIME_IMMUTABLE);
    $res = $stmt->executeQuery();
    return $res->fetchOne();
  }

  public function update_balance(
    int $account_id,
    int $amount,
    Schema $schema,
  ):void
  {
    $stmt = $this->db->prepare('insert into ' . $schema->str() . '.balance (account_id, amount, balance)
      values (:account_id, :amount, (select coalesce(b.balance, 0) + :amount
      from (values(0)) as d
      left join ' . $schema->str() . '.balance as b
      on b.account_id = :account_id
      order by b.id desc limit 1))');
    $stmt->bindValue('account_id', $account_id, Types::INTEGER);
    $stmt->bindValue('amount', $amount, Types::INTEGER);
    $stmt->executeStatement();
  }

  public function get_balance_ary(
    Schema $schema,
  ):array
  {
    $balance_ary = [];

    $stmt = $this->db->prepare('select distinct on(account_id) balance, account_id
      from ' . $schema->str() . '.balance
      order by account_id, id desc');

    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $balance_ary[$row['account_id']] = $row['balance'];
    }

    return $balance_ary;
  }

  public function get_balance_ary_on_date(
    \DateTimeImmutable $datetime,
    Schema $schema,
  ):array
  {
    $balance_ary = [];

    $stmt = $this->db->prepare('select distinct on(account_id) balance, account_id
      from ' . $schema->str() . '.balance
      where created_at <= :datetime
      order by account_id, id desc');
    $stmt->bindValue('datetime', $datetime, Types::DATETIME_IMMUTABLE);
    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $balance_ary[$row['account_id']] = $row['balance'];
    }

    return $balance_ary;
  }
}
