<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\ItemAccessService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessageRepository
{
	protected Db $db;
	protected ItemAccessService $item_access_service;

	public function __construct(
		Db $db,
		ItemAccessService $item_access_service
	)
	{
		$this->db = $db;
		$this->item_access_service = $item_access_service;
	}

	public function get_visible_for_page(int $id, string $schema):array
	{
        $stmt = $this->db->executeQuery('select *
            from ' . $schema . '.messages
            where id = ?
                and access in (?)', [
                $id,
                $this->item_access_service->get_visible_ary_for_page()
            ], [
                \PDO::PARAM_INT,
                Db::PARAM_STR_ARRAY,
            ]);

        $data = $stmt->fetch();

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
