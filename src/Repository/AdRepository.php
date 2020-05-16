<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdRepository
{
	protected $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
	}

	public function getAll(string $schema):array
	{

	}

	public function get(int $id, string $schema):array
	{
		$data = $this->db->fetchAssoc('select *
			from ' . $schema . '.messages
			where id = ?', [$id]);

		if (!$data)
		{
			throw new NotFoundHttpException('Ad ' . $id . ' does not exist in %s');
        }

		return $data;
	}
}
