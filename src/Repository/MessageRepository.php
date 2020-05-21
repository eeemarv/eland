<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessageRepository
{
	protected Db $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
	}

	public function get(int $id, string $schema):array
	{
		$data = $this->db->fetchAssoc('select *
			from ' . $schema . '.messages
			where id = ?', [$id]);

		if (!$data)
		{
			throw new NotFoundHttpException('Message ' . $id . ' not found.');
        }

		return $data;
	}

	public function del(int $id, string $schema):bool
	{
		return $this->db->delete($schema . '.messages',
			['id' => $id]) ? true : false;
	}
}
