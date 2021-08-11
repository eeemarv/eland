<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function getAll(string $schema)
	{

	}

	public function get(int $id, string $schema):array
	{
		$contact = $this->db->fetchAssociative('select *
			from ' . $schema . '.contact
			where id = ?', [$id]);

		if ($contact === false)
		{
			throw new NotFoundHttpException('Contact ' . $id . ' not found.');
        }

		return $contact;
	}

	public function get_all_for_user(
		int $user_id,
		string $schema
	):array
	{
		$stmt = $this->db->executeQuery('select c.*, tc.abbrev
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc
			where c.access in (?)
				and c.user_id = ?
				and c.id_type_contact = tc.id',
				[$user_id],
				[\PDO::PARAM_INT]
		);

		return $stmt->fetchAllAssociative();
	}

}
