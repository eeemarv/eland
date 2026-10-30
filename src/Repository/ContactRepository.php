<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
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

  public function get_filtered(
    string|null $value,
    int|null $user_id,
    int|null $contact_type_id,
    array|null $access_ary,
    string|null $user_status,
    int $start,
    int $limit,
    string $order_by,
    bool $asc,
    bool $intersystem_enabled,
    \DateTimeImmutable $new_user_treshold,   
    Schema $schema,
  ):array
  {
    $sql = [
      'where' => [
        'common' => '1 = 1',
      ],
      'params'  => [],
      'types'   => [],
    ];

    if (isset($user_id))
    {
      $sql['where']['user_id']= 'c.user_id = :user_id';
      $sql['params']['user_id']= $user_id;
      $sql['types']['user_id']= Types::INTEGER;
    }

    if (isset($value))
    {
      $sql['where']['value']= '(c.value ilike :value or c.comments ilike :value)';
      $sql['params']['value']= '%' . $value . '%';
      $sql['types']['value']= Types::STRING;
    }

    if (isset($contact_type_id))
    {
      $sql['where']['contact_type_id']= 'c.id_type_contact = :contact_type_id';
      $sql['params']['contact_type_id']= $contact_type_id;
      $sql['types']['contact_type_id']= Types::INTEGER;
    }

    if (isset($access_ary) && count($access_ary))
    {
      $where_or = [];

      if (in_array('admin', $access_ary))
      {
        $where_or[] = 'c.access = \'admin\'';
      }

      if (in_array('user', $access_ary))
      {
        $where_or[] = 'c.access = \'user\'';

        if (!$intersystem_enabled)
        {
          $where_or[] = 'c.access = \'guest\'';
        }
      }

      if (in_array('guest', $access_ary) && $intersystem_enabled)
      {
        $where_or[] = 'c.access = \'guest\'';
      }

      if (count($where_or))
      {
        $sql['where']['access'] = ' (' . implode(' or ', $where_or) . ') ';
      }
    }

    if (isset($user_status))
    {
      switch ($user_status)
      {
        case 'new':
          $sql['where']['activated_at']= 'u.activated_at > :activated_at and u.is_active';
          $sql['params']['activated_at']= $new_user_treshold;
          $sql['types']['activated_at']= Types::DATETIME_IMMUTABLE;
          break;
        case 'leaving':
          $sql['where']['ustatus'] = 'u.is_leaving and u.is_active';
          break;
        case 'active':
          $sql['where']['ustatus'] = 'u.is_active';
          break;
        case 'pre-active':
          $sql['where']['ustatus']= 'not u.is_active 
            and u.is_activated_at is null';
          break;
        case 'post-active':
          $sql['where']['ustatus']= 'not u.is_active 
            and u.is_activated_at is not null';
          break;
        case 'intersystem':
          $sql['where']['ustatus']= '(u.remote_schema is not null 
            or u.remote_email is not null)';
          break;
        default:
          break;
      }
    }

    $sql['params']['limit'] = $limit;
    $sql['types']['limit'] = Types::INTEGER;
    $sql['params']['offset'] = $start;
    $sql['types']['offset'] = Types::INTEGER;

    $contacts = [];

    $sql_where = implode(' and ', $sql['where']);

    $query = 'select c.*, tc.abbrev, 
      u.code, u.name, u.is_active,
      u.activated_at, u.is_leaving
      from ' . $schema->str() . '.contact c
      inner join ' . $schema->str() . '.type_contact tc
        on c.id_type_contact = tc.id
      inner join ' . $schema->str() . '.users u
        on c.user_id = u.id
      where ' . $sql_where . '
      order by ' . $order_by . ' ' .
      ($asc ? 'asc' : 'desc') . '
      limit :limit offset :offset';

    $res = $this->db->executeQuery($query, $sql['params'], $sql['types']);

    while ($row = $res->fetchAssociative())
    {
      $contacts[$row['id']] = $row;
    }

    $sql_all = $sql;
    unset($sql_all['params']['limit']);
    unset($sql_all['types']['limit']);
    unset($sql_all['params']['offset']);
    unset($sql_all['types']['offset']);

    $sql_all_where = implode(' and ', $sql_all['where']);

    $sql_omit_access = $sql_all;
    unset($sql_omit_access['where']['access']);
    $sql_omit_access_where = implode(' and ', $sql_omit_access['where']);

    $res = $this->db->executeQuery('select
      count(c.*) filter
        (where ' . $sql_all_where . ') as row_count,
      count(c.*) filter
        (where ' . $sql_omit_access_where . ' and access = \'admin\') as admin,
      count(c.*) filter
        (where ' . $sql_omit_access_where . ' and access = \'user\') as user,
      count(c.*) filter
        (where ' . $sql_omit_access_where . ' and access = \'guest\') as guest
      from ' . $schema->str() . '.contact c
      inner join ' . $schema->str() . '.users u
        on c.user_id = u.id',
      $sql_all['params'],
      $sql_all['types']);

    $count_ary = $res->fetchAssociative();

    if (!$intersystem_enabled)
    {
      $count_ary['user'] += $count_ary['guest'];
    }

    return [
      'contacts'  => $contacts,
      'count_ary' => $count_ary,
    ];
  }

  public function set_bulk_access(
    string $access,
    array $selected_ids,
    Schema $schema,
  )
  {
    $affected_rows = (int) $this->db->executeStatement('
      update ' . $schema->str() . '.contact
      set access = :access
      where id in (:selected_ids)', [
      'access'  => $access,
      'selected_ids'  => $selected_ids,
    ], [
      'access'  => Types::STRING,
      'selected_ids'  => ArrayParameterType::INTEGER,
    ]);
    
    return $affected_rows;
  }

  public function get_selected(
    array $selected_ids,
    Schema $schema,
  ):array
  {
    $contacts = [];

    $res = $this->db->executeQuery('select * 
      from ' . $schema->str() . '.contact
      where id in (:selected_ids)', [
        'selected_ids'  => $selected_ids,
      ], [
        'selected_ids'  => ArrayParameterType::INTEGER,
      ]);

    while ($row = $res->fetchAssociative())
    {
      $contacts[$row['id']] = $row;
    }

    return $contacts;
  }
}
