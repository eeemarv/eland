<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\Contacts\ContactsCommand;
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
		$stmt =  $this->db->prepare('select *
            from ' . $schema . '.type_contact
            where id = :contact_type_id');

		$stmt->bindValue('contact_type_id', $contact_type_id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();

		$contact_type = $res->fetchAssociative();

		if ($contact_type === false)
		{
			throw new NotFoundHttpException('Contact type with id ' . $contact_type_id . ' not found.');
		}

		return $contact_type;
	}

	public function get_contact_type_by_abbrev(
		string $abbrev,
		string $schema
	):array
	{
		$stmt = $this->db->prepare('select *
            from ' . $schema . '.type_contact
            where abbrev = :abbrev');

		$stmt->bindValue('abbrev', $abbrev, \PDO::PARAM_STR);
		$res = $stmt->executeQuery();
		$contact_type = $res->fetchAssociative();

		if ($contact_type === false)
		{
			throw new NotFoundHttpException('Contact type with abbrev ' . $abbrev . ' not found.');
		}

		return $contact_type;
	}

	public function get_count_for_contact_type(
		int $contact_type_id,
		string $schema
	):int
	{
		$stmt = $this->db->prepare('select count(*)
            from ' . $schema . '.contact
            where id_type_contact = :contact_type_id');

		$stmt->bindValue('contact_type_id', $contact_type_id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function get_mail_count_except_for_user(
		string $email_address,
		int $user_id,
		string $schema
	)
	{
		$email_lowercase = strtolower($email_address);

		$stmt = $this->db->prepare('select count(c.*)
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc, ' .
				$schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and u.id <> :user_id
				and lower(c.value) = :email_lowercase');

		$stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
		$stmt->bindValue('email_lowercase', $email_lowercase, \PDO::PARAM_STR);
		$res = $stmt->executeQuery();

		return $res->fetchOne();
	}

	public function get_mail_count_for_user(
		int $user_id,
		string $schema
	)
	{
		$stmt = $this->db->prepare('select count(c.*)
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc, ' .
				$schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.id = :user_id');

		$stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();

		return $res->fetchOne();
	}

	public function get_all_contact_types(
		string $schema
	):array
	{
		return $this->db->fetchAllAssociative('select tc.*
			from ' . $schema . '.type_contact tc
			order by tc.id asc') ?: [];
	}

	public function get_all_contact_types_with_count(
		string $schema
	):array
	{
		return $this->db->fetchAllAssociative('select tc.*, count(c.*)
			from ' . $schema . '.type_contact tc
			left join ' . $schema . '.contact c
			on tc.id = c.id_type_contact
			group by tc.id order by tc.id asc;') ?: [];
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

	public function update(
		ContactsCommand $command,
		string $schema
	):bool
	{
		$update_ary = [
			'id_type_contact'	=> $command->contact_type_id,
			'value'				=> $command->value,
			'comments'			=> $command->comments,
			'access'			=> $command->access,
		];

		$id = $command->id;

		if (!$id)
		{
			return false;
		}

		return $this->db->update($schema . '.contact',
			$update_ary,
			['id' => $id],
			['id' => \PDO::PARAM_INT]) ? true : false;
	}

	public function del(int $id, string $schema):bool
	{
		return $this->db->delete($schema . '.contact',
			['id' => $id],
			['id' => \PDO::PARAM_INT]) ? true : false;
	}

	public function get(int $id, string $schema):array
	{

		$stmt = $this->db->prepare('select *
			from ' . $schema . '.contact
			where id = :id');

		$stmt->bindValue('id', $id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();
		$contact = $res->fetchAssociative();

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
		$stmt = $this->db->prepare('select c.*, tc.abbrev
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc
			where c.user_id = :user_id
				and c.id_type_contact = tc.id');

		$stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
		$res = $stmt->executeQuery();

		return $res->fetchAllAssociative() ?: [];
	}
}
