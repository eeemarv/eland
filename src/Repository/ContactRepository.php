<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactRepository
{
	protected $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
	}

	public function getAll(string $schema)
	{

	}

	public function get(int $id, string $schema):array
	{
		$contact = $this->db->fetchAssoc('select *
			from ' . $schema . '.contact
			where id = ?', [$id]);

		if ($contact === false)
		{
			throw new NotFoundHttpException('Contact ' . $id . ' not found.');
        }

		return $contact;
	}

	public function get_all_of_user(
		int $user_id,
		array $access_ary,
		string $user_schema
	):array
	{
		$stmt = $this->db->executeQuery('select c.value, tc.abbrev
			from ' . $user_schema . '.contact c, ' .
				$user_schema . '.type_contact tc
			where c.access in (?)
				and c.user_id = ?
				and c.id_type_contact = tc.id',
				[$access_ary, $user_id],
				[Db::PARAM_STR_ARRAY, \PDO::PARAM_INT]
		);

		return $stmt->fetchAll();
	}

}
