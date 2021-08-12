<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\ContactTypes\ContactTypesCommand;
use Doctrine\DBAL\Connection as Db;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function insert_contact_type(
		ContactTypesCommand $command,
		string $schema
	):int
	{
		$data_ary = (array) $command;
		unset($data_ary['id']);
		$this->db->insert($schema . '.type_contact', $data_ary);
		return (int) $this->db->lastInsertId($schema . '.type_contact_id_seq');
	}

	public function update_contact_type(
		ContactTypesCommand $command,
		string $schema
	):void
	{
		if (!isset($command->id))
		{
			throw new Exception('no id set for update.');
		}

		$id = $command->id;

		$this->db->update($schema . '.type_contact',
			(array) $command,
			['id' => $id]
		);
	}

	public function del_contact_type(
		int $contact_type_id,
		string $schema
	):bool
	{
		return $this->db->delete($schema . '.type_contact',
			['id' => $contact_type_id]) ? true : false;
	}

	public function get_contact_type(
		int $contact_type_id,
		string $schema
	):array
	{
		return $this->db->fetchAssociative('select *
            from ' . $schema . '.type_contact
            where id = ?',
			[$contact_type_id],
			[\PDO::PARAM_INT]);
	}

	public function get_count_for_contact_type(
		int $contact_type_id,
		string $schema
	):int
	{
		return $this->db->fetchOne('select count(*)
            from ' . $schema . '.contact
            where id_type_contact = ?',
            [$contact_type_id],
			[\PDO::PARAM_INT]);
	}

	public function get_all_contact_types(
		string $schema
	):array
	{
		return $this->db->fetchAllAssociative('select *
			from ' . $schema . '.type_contact tc');
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
