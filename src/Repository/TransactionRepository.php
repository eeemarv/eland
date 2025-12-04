<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

  // not used yet(?)
	public function get_count_for_user_id(
    int $user_id,
    Schema $schema,
  ):int
	{
    $stmt = $this->db->prepare('select count(*)
      from ' . $schema->str() . '.transactions
      where id_to = :user_id or id_from = :user_id');
		$stmt->bindValue('user_id', $user_id, Types::INTEGER);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function get(
    int $id,
    Schema $schema,
  ):array
	{
		$stmt = $this->db->prepare('select *
			from ' . $schema->str() . '.transactions
			where id = :id');
		$stmt->bindValue('id', $id, Types::INTEGER);
		$res = $stmt->executeQuery();
		$data = $res->fetchAssociative();

		if ($data === false)
		{
			throw new NotFoundHttpException('Transaction ' . $id . ' does not exist');
		}

		return $data;
	}

	public function get_next_id(
    int $id,
    Schema $schema,
  ):int|false
	{
		$stmt = $this->db->prepare('select id
			from ' . $schema->str() . '.transactions
			where id > :id
			order by id asc
			limit 1', [$id]);
		$stmt->bindValue('id', $id, Types::INTEGER);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function get_prev_id(
    int $id,
    Schema $schema,
  ):int|false
	{
		$stmt = $this->db->prepare('select id
			from ' . $schema->str() . '.transactions
			where id < :id
			order by id desc
			limit 1');
		$stmt->bindValue('id', $id, Types::INTEGER);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function update_description(
    int $id,
    string $description,
    Schema $schema,
  )
	{
		$this->db->update($schema->str() . '.transactions', [
      'description'	=> $description,
    ], ['id' => $id]);
	}

  public function get_activity_for_each_user(
    \DateTimeImmutable $since,
    int|null $exclude_account_id,
    Schema $schema,
  ):array
  {
    $ary = [];
    // an not existing value when not set
    $exclude_id = $exclude_account_id ?? -1;
    $stmt = $this->db->prepare('with a_transactions as (
      select id_to as user_id,
        \'in\' as direction,
        amount
      from ' . $schema->str() . '.transactions
      where created_at >= :since
        and id_from <> :exclude_id
      union all
      select id_from as user_id,
        \'out\' as direction,
        amount
      from ' . $schema->str() . '.transactions
      where created_at >= :since
        and id_to <> :exclude_id
      )
      select user_id,
        count(*) as trans_total,
        count(*) filter (where direction = \'in\') as trans_in,
        count(*) filter (where direction = \'out\') as trans_out,
        sum(amount) as amount_total,
        sum(amount) filter (where direction = \'in\') as amount_in,
        sum(amount) filter (where direction = \'out\') as amount_out
      from a_transactions
      group by user_id');
    $stmt->bindValue('since', $since, Types::DATETIME_IMMUTABLE);
    $stmt->bindValue('exclude_id', $exclude_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    while ($row = $res->fetchAssociative())
    {
      $ary[$row['user_id']] = $row;
    }
    return $ary;
  }
}
