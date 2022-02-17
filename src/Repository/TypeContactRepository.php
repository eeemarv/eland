<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TypeContactRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function getAll(string $schema):array
	{
		return $this->db->fetchAllAssociative('select tc.*
			from ' . $schema . '.type_contact tc');
	}

	public function getAllWithCount(string $schema):array
	{
		return $this->db->fetchAllAssociative('select tc.*, count(c.*)
			from ' . $schema . '.type_contact tc, ' . $schema . '.contact c
			where c.id_type_contact = tc.id
			group by tc.id');
	}

	public function getAllAbbrev(string $schema):array
	{
		$ary = [];

		$stmt = $this->db->prepare('select id, abbrev from ' . $schema . '.type_contact');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$ary[$row['id']] = $row['abbrev'];
		}

		return $ary;
	}

	public function get(int $id, string $schema):array
	{
		$data = $this->db->fetchAssociative('select *
			from ' . $schema . '.type_contact
			where id = ?', [$id]);

		if (!$data)
		{
			throw new NotFoundHttpException(sprintf(
				'Contact type %d does not exist in %s',
				$id, __CLASS__));
        }

		return $data;
	}

	public function update(int $id, string $schema, array $data)
	{
		$this->db->update($schema . '.type_contact', $data, ['id' => $id]);
	}

	public function insert(string $schema, array $data)
	{
		$this->db->insert($schema . '.type_contact', $data);
	}

	public function delete(int $id, string $schema)
	{
		$this->db->delete($schema . '.type_contact', ['id' => $id]);
	}
}
