<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get_count_for_user_id(int $user_id, string $schema):int
	{
        $stmt = $this->db->prepare('select count(*)
            from ' . $schema . '.transactions
            where id_to = :user_id or id_from = :user_id');
		$stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function get(int $id, string $schema):array
	{
		$stmt = $this->db->prepare('select *
			from ' . $schema . '.transactions
			where id = :id');
		$stmt->bindValue('id', $id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();
		$data = $res->fetchAssociative();

		if ($data === false)
		{
			throw new NotFoundHttpException('Transaction ' . $id . ' does not exist');
		}

		return $data;
	}

	public function get_next_id(int $id, string $schema):int|false
	{
		$stmt = $this->db->prepare('select id
			from ' . $schema . '.transactions
			where id > :id
			order by id asc
			limit 1', [$id]);
		$stmt->bindValue('id', $id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function get_prev_id(int $id, string $schema):int|false
	{
		$stmt = $this->db->prepare('select id
			from ' . $schema . '.transactions
			where id < :id
			order by id desc
			limit 1');
		$stmt->bindValue('id', $id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function updateDescription(int $id, string $description, string $schema)
	{
		$this->db->update($schema . '.transactions', ['description'	=> $description], ['id' => $id]);
	}
}
