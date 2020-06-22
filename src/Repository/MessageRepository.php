<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessageRepository
{
	protected Db $db;

	public function __construct(
		Db $db
	)
	{
		$this->db = $db;
	}

	public function get(int $id, string $schema):array
	{
        $message = $this->db->fetchAssoc('select *
            from ' . $schema . '.messages
            where id = ?', [
                $id
            ]);

		if (!$message)
		{
			throw new NotFoundHttpException('Message ' . $id . ' not found.');
        }

		return $message;
	}

	public function del(int $id, string $schema):bool
	{
		return $this->db->delete($schema . '.messages',
			['id' => $id]) ? true : false;
	}

	public function insert(array $message, string $schema):int
	{
		$this->db->insert($schema . '.messages', $message);
		return (int) $this->db->lastInsertId($schema . '.messages_id_seq');
	}

	public function update(array $message, int $id, string $schema):bool
	{
		return $this->db->update($schema . '.messages', $message, ['id' => $id]) ? true : false;
	}

	public function get_count_for_user_id(int $user_id, string $schema):int
	{
        return $this->db->fetchColumn('select count(*)
            from ' . $schema . '.messages
            where user_id = ?', [$user_id]);
	}

	public function del_for_user_id(int $user_id, string $schema):void
	{
		$this->db->delete($schema . '.messages', ['user_id' => $user_id]);
	}

	public function add_image_file(string $image_filename, int $id, string $schema):void
	{
		$this->db->executeUpdate('update ' . $schema . '.messages
			set image_files = coalesce(image_files, \'[]\') || ?::jsonb
			where id = ?',
			[$image_filename, $id],
			[Types::JSON, \PDO::PARAM_INT]);
	}

	public function update_image_files(array $image_files, int $id, string $schema):void
	{
        $image_files = json_encode(array_values($image_files));

		$this->db->update($schema . '.messages',
			['image_files' => $image_files],
			['id' => $id]);
	}

	public function get_max_id(string $schema):int
	{
		return $this->db->fetchColumn('select max(id)
			from ' . $schema . '.messages') ?: 0;
	}
}
