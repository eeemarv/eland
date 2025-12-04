<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class ContactRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function insert_contact_type(
    string $abbrev,
    string $name,
		Schema $schema
	):int
	{
    $this->db->executeStatement('insert into ' .
      $schema->str() . '.type_contact
      (abbrev, name)
      values
      (:abbrev, :name)', [
        'abbrev'  => $abbrev,
        'name'   => $name,
      ], [
        'abbrev'  => Types::STRING,
        'name'   => Types::STRING,
      ]);
		return (int) $this->db->lastInsertId($schema->str() . '.type_contact_id_seq');
	}

	public function update_contact_type(
    int $id,
    string $abbrev,
    string $name,
		Schema $schema
	):void
	{
		$this->db->executeStatement('update ' .
      $schema->str() . '.type_contact
      set abbrev = :abbrev, name = :name
      where id = :id', [
        'abbrev'  => $abbrev,
        'name'    => $name,
        'id'      => $id,
      ], [
        'abbrev'  => Types::STRING,
        'name'    => Types::STRING,
        'id'      => Types::INTEGER,
      ],
		);
	}

	public function del_contact_type(
		int $id,
		Schema $schema
	):bool
	{
		return $this->db->executeStatement('delete from ' .
      $schema->str() . '.type_contact
      where id = :id', [
        'id'  => $id,
      ], [
        'id'  => Types::INTEGER
      ]) ? true : false;
	}

	public function get_contact_type(
		int $id,
		Schema $schema
	):array
	{
		$stmt =  $this->db->prepare('select *
      from ' . $schema->str() . '.type_contact
      where id = :id');

		$stmt->bindValue('id', $id, Types::INTEGER);
		$res = $stmt->executeQuery();

		$contact_type = $res->fetchAssociative();

		if ($contact_type === false)
		{
			throw new \Exception('Contact type with id ' . $id . ' not found.');
		}

		return $contact_type;
	}

	public function get_contact_type_by_abbrev(
		string $abbrev,
		Schema $schema
	):array
	{
		$stmt = $this->db->prepare('select *
      from ' . $schema->str() . '.type_contact
      where abbrev = :abbrev');

		$stmt->bindValue('abbrev', $abbrev, Types::STRING);
		$res = $stmt->executeQuery();
		$contact_type = $res->fetchAssociative();

		if ($contact_type === false)
		{
			throw new \Exception('Contact type with abbrev ' . $abbrev . ' not found.');
		}

		return $contact_type;
	}

	public function get_count_for_contact_type(
		int $contact_type_id,
		Schema $schema
	):int
	{
		$stmt = $this->db->prepare('select count(*)
      from ' . $schema->str() . '.contact
      where id_type_contact = :contact_type_id');

		$stmt->bindValue('contact_type_id', $contact_type_id, Types::INTEGER);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function get_mail_count_except_for_user(
		string $email_address,
		int $user_id,
		Schema $schema
	)
	{
		$email_lowercase = strtolower($email_address);

		$stmt = $this->db->prepare('select count(c.*)
			from ' . $schema->str() . '.contact c, ' .
				$schema->str() . '.type_contact tc, ' .
				$schema->str() . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and u.id <> :user_id
				and lower(c.value) = :email_lowercase');

		$stmt->bindValue('user_id', $user_id, Types::INTEGER);
		$stmt->bindValue('email_lowercase', $email_lowercase, Types::STRING);
		$res = $stmt->executeQuery();

		return $res->fetchOne();
	}

	public function get_mail_count_for_user(
		int $user_id,
		Schema $schema,
	)
	{
		$stmt = $this->db->prepare('select count(c.*)
			from ' . $schema->str() . '.contact c, ' .
				$schema->str() . '.type_contact tc, ' .
				$schema->str() . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.id = :user_id');

		$stmt->bindValue('user_id', $user_id, Types::INTEGER);
		$res = $stmt->executeQuery();

		return $res->fetchOne();
	}

	public function get_all_contact_types(
		Schema $schema
	):array
	{
		return $this->db->fetchAllAssociative('select tc.*
			from ' . $schema->str() . '.type_contact tc
			order by tc.id asc');
	}

	public function get_all_contact_types_with_count(
		Schema $schema
	):array
	{
		return $this->db->fetchAllAssociative('select tc.*, count(c.*)
			from ' . $schema->str() . '.type_contact tc
			left join ' . $schema->str() . '.contact c
			on tc.id = c.id_type_contact
			group by tc.id order by tc.id asc;');
	}

	public function insert(
    string $value,
    string|null $comments,
    int $contact_type_id,
    string $access,
    int $user_id,
		int|null $created_by,
		Schema $schema
	):int
	{
		$insert_ary = [
      'value'   => $value,
      'id_type_contact' => $contact_type_id,
      'access'  => $access,
      'user_id' => $user_id,
    ];
		$type_ary = [
      'value'   => Types::STRING,
      'id_type_contact' => Types::INTEGER,
      'access'  => Types::STRING,
      'user_id' => Types::INTEGER,
    ];

		if (isset($created_by))
		{
			$insert_ary['created_by'] = $created_by;
			$type_ary['created_by'] = Types::INTEGER;
		}

    if (isset($comments))
    {
			$insert_ary['comments'] = $comments;
			$type_ary['comments'] = Types::STRING;
    }

		$this->db->insert($schema->str() . '.contact', $insert_ary, $type_ary);

		return (int) $this->db->lastInsertId($schema->str() . '.contact_id_seq');
	}

	public function update(
    int $id,
    int $contact_type_id,
    string $value,
    string|null $comments,
    string $access,
		Schema $schema
	):void
	{
		$update_ary = [
			'id_type_contact'	=> $contact_type_id,
			'value'				=> $value,
			'comments'			=> $comments,
			'access'			=> $access,
		];
		$type_ary = [
			'id_type_contact'	=> Types::INTEGER,
			'value'				=> Types::STRING,
			'comments'			=> Types::STRING,
			'access'			=> Types::STRING,
      'id'          => Types::INTEGER,
		];

		$this->db->update($schema->str() . '.contact',
			$update_ary,
			['id' => $id],
			$type_ary);
	}

	public function del(
    int $id,
    Schema $schema
  )
	{
		return $this->db->delete($schema->str() . '.contact',
			['id' => $id],
			['id' => Types::INTEGER]);
	}

	public function get(
    int $id,
    Schema $schema,
  ):array
	{

		$stmt = $this->db->prepare('select *
			from ' . $schema->str() . '.contact
			where id = :id');

		$stmt->bindValue('id', $id, Types::INTEGER);
		$res = $stmt->executeQuery();
		$contact = $res->fetchAssociative();

		if ($contact === false)
		{
			throw new \Exception('Contact ' . $id . ' not found.');
    }

		return $contact;
	}

	public function get_all_for_user(
		int $user_id,
		Schema $schema
	):array
	{
		$stmt = $this->db->prepare('select c.*, tc.abbrev
			from ' . $schema->str() . '.contact c, ' .
				$schema->str() . '.type_contact tc
			where c.user_id = :user_id
				and c.id_type_contact = tc.id');

		$stmt->bindValue('user_id', $user_id, Types::INTEGER);
		$res = $stmt->executeQuery();

		return $res->fetchAllAssociative();
	}
}
