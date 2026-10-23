<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class ContactRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

  /**
   * this enforces the new column is_address
   * use in geocoding
   */
  public function get_address(
    int $contact_id,
    Schema $schema,
  ):array|false
  {
    $stmt = $this->db->prepare('select value,
      comments, latitude, longitude, access,
      geocoded_at, geocode_failed_at,
      geocode_fail_count
      from ' . $schema->str() . '.contact
      where is_address
        and contact_id = :contact_id');
    $stmt->bindValue('contact_id', $contact_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    return $res->fetchAssociative();
  }

  public function set_address_geocode_failed(
    int $contact_id,
    Schema $schema,
  ):void
  {
    $this->db->executeStatement('update ' .
      $schema->str() . '.contact
      set geocode_fail_count = geocode_fail_count + 1,
        geocode_failed_at = timezone(\'utc\', now()),
        longitude = null,
        latitude = null
      where is_address
        and contact_id = :contact_id', [
        'contact_id' => $contact_id,
      ], [
        'contact_id' => Types::INTEGER,
      ]);
  }

  public function set_address_geocoded(
    int $contact_id,
    string $latitude,
    string $longitude,
    Schema $schema,
  ):void
  {
    $this->db->executeStatement('update ' .
      $schema->str() . '.contact
      set geocoded_at = timezone(\'utc\', now()),
        longitude = :longitude,
        latitude = :latitude,
        geocode_fail_count = 0,
        geocode_failed_at = null
      where is_address
        and contact_id = :contact_id', [
        'contact_id' => $contact_id,
        'latitude' => $latitude,
        'longitude' => $longitude,
      ], [
        'contact_id' => Types::INTEGER,
        'latitude' => Types::STRING,
        'longitude' => Types::STRING,
      ]);
  }

	public function insert_contact_type(
    string $abbrev,
    string $name,
		Schema $schema
	):int
	{
    $stmt = $this->db->prepare('insert into ' .
      $schema->str() . '.type_contact
      (abbrev, name)
      values
      (:abbrev, :name) returning id');
    $stmt->bindValue('abbrev', $abbrev, Types::STRING);
    $stmt->bindValue('name', $name, Types::STRING);
    $res = $stmt->executeQuery();
    $id = (int) $res->fetchOne();
		return $id;
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
			throw new \Exception(
        'Contact type with id ' . $id . ' not found.'
      );
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
		Schema $schema,
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
    $stmt = $this->db->prepare('insert into ' . $schema->str() . '.contact
      (value, id_type_contact, access, user_id, created_by, comments)
      values
      (:value, :id_type_contact, :access, :user_id, :created_by, :comments)
      returning id');
    $stmt->bindValue('value', $value, Types::STRING);
    $stmt->bindValue('id_type_contact', $contact_type_id, Types::INTEGER);
    $stmt->bindValue('access', $access, Types::STRING);
    $stmt->bindValue('user_id', $user_id, Types::INTEGER);
    $stmt->bindValue('created_by', $created_by, Types::INTEGER);
    $stmt->bindValue('comments', $comments, Types::STRING);
    $res = $stmt->executeQuery();
    $id = (int) $res->fetchOne();
    return $id;
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
			$update_ary, [
        'id' => $id,
      ], $type_ary);
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
