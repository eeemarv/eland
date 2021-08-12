<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\Contacts\ContactsCommand;
use App\Command\ContactTypes\ContactTypesCommand;
use App\Service\SessionUserService;
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
		$contact_type =  $this->db->fetchAssociative('select *
            from ' . $schema . '.type_contact
            where id = ?',
			[$contact_type_id],
			[\PDO::PARAM_INT]);

		if ($contact_type === false)
		{
			throw new NotFoundHttpException('Contact type with id ' . $contact_type_id . ' not found.');
		}

		return $contact_type;
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

	public function get_mail_count_except_for_user(
		string $mail_address,
		int $user_id,
		string $schema
	)
	{
		$mail_address_lowercase = strtolower($mail_address);

		$mail_count = $this->db->fetchOne('select count(c.*)
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc, ' .
				$schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and u.id <> ?
				and lower(c.value) = ?',
				[$user_id, $mail_address_lowercase],
				[\PDO::PARAM_INT, \PDO::PARAM_STR]);

		return $mail_count;
	}

	public function get_all_contact_types(
		string $schema
	):array
	{
		return $this->db->fetchAllAssociative('select *
			from ' . $schema . '.type_contact tc');
	}

	public function insert(
		ContactsCommand $command,
		?int $created_by,
		string $schema
	):int
	{
		$insert_ary = (array) $command;
		unset($insert_ary['contact_type_id']);
		unset($insert_ary['id']);
		$insert_ary['id_type_contact'] = $command->contact_type_id;

		if (isset($created_by))
		{
			$insert_ary['created_by'] = $created_by;
		}

		$this->db->insert($schema . '.contact', $insert_ary);
		return (int) $this->db->lastInsertId($schema . '.contact_id_seq');
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
