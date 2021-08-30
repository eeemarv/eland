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
        return $this->db->fetchColumn('select count(*)
            from ' . $schema . '.transactions
            where id_to = ? or id_from = ?', [$user_id, $user_id]);
	}

	public function get(int $id, string $schema):array
	{
		$data = $this->db->fetchAssoc('select *
			from ' . $schema . '.transactions
			where id = ?', [$id]);

		if (!$data)
		{
			throw new NotFoundHttpException(sprintf('Transaction %d does not exist in %s',
				$id, __CLASS__));
		}

		return $data;
	}

	public function getNext(int $id, string $schema)
	{
		return $this->db->fetchColumn('select id
			from ' . $schema . '.transactions
			where id > ?
			order by id asc
			limit 1', [$id]) ?? null;
	}

	public function getPrev(int $id, string $schema)
	{
		return $this->db->fetchColumn('select id
			from ' . $schema . '.transactions
			where id < ?
			order by id desc
			limit 1', [$id]) ?? null;
	}

	public function updateDescription(int $id, string $description, string $schema)
	{
		$this->db->update($schema . '.transactions', ['description'	=> $description], ['id' => $id]);
	}
}
