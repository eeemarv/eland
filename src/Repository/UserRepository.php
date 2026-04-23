<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\AddressAry;
use App\DTO\Schema;
use App\Service\ConfigService;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use LogicException;
use Symfony\Component\Mime\Address;

class UserRepository
{
	public function __construct(
		private readonly Db $db,
    private readonly ConfigService $config_service,
	)
	{
	}

  public function get_email_addresses(
    int $user_id,
    Schema $schema,
    bool $active_only = true,
  ):AddressAry
  {
    $sql_active = $active_only ? ' and u.status in (1, 2)' : '';

    $stmt = $this->db->prepare('select c.value, u.name
      from ' . $schema->str() . '.contact c, ' .
        $schema->str() . '.type_contact tc, ' .
        $schema->str() . '.users u
      where c.id_type_contact = tc.id
        and tc.abbrev = \'mail\'
        and c.user_id = :user_id
        and c.user_id = u.id' . $sql_active);
    $stmt->bindValue('user_id', $user_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    $ary = [];

    while ($row = $res->fetchAssociative())
    {
      $ary[] = new Address($row['value'], $row['name']);
    }

    return new AddressAry($ary);
  }

  public function get_users_with_email_addresses(
    array $user_ids,
    Schema $schema
  ):array
  {
    $users = [];

    $res = $this->db->executeQuery('select u.*,
      coalesce(jsonb_agg(c.value) filter(where c.value is not null), \'[]\') as email_addresses
      from ' . $schema->str() . '.users u
      left join ' . $schema->str() . '.contact c
        on c.user_id = u.id
          and c.id_type_contact = (select t.id
            from ' . $schema->str() . '.type_contact t
            where t.abbrev = \'mail\')
      where u.id in (:user_ids)
      group by u.id
      order by u.code asc', [
        'user_ids' => $user_ids,
      ], [
        'user_ids' => ArrayParameterType::INTEGER,
      ]);

    while (($row = $res->fetchAssociative()))
    {
      $users[$row['id']] = [
        ...$row,
        'email_addresses' => json_decode($row['email_addresses']),
      ];
    }

    return $users;
  }

	public function get_account_str(
    int $id,
    Schema $schema,
  ):string|false
	{
    $account_str = $this->db->fetchOne('select trim(concat(coalesce(code,\'\'), \' \', coalesce(name, \'\')))
      from ' . $schema->str() . '.users
			where id = :id', [
        'id'  => $id,
      ], [
        'id'  => Types::INTEGER,
      ]);

		return $account_str;
	}

	public function count_email(
		string $email,
		Schema $schema
	):int
	{
		$email_lowercase = strtolower($email);

		return $this->db->fetchOne('select count(c.*)
			from ' . $schema->str() . '.contact c, ' .
				$schema->str() . '.type_contact tc
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and lower(c.value) = :email_lowercase', [
      'email_lowercase' => $email_lowercase,
    ], [
      'email_lowercase' => Types::STRING,
    ]);
	}

	public function count_active_by_email(
		string $email,
		Schema $schema
	):int
	{
		$email_lowercase = strtolower($email);

		return $this->db->fetchOne('select count(c.*)
			from ' . $schema->str() . '.contact c, ' .
				$schema->str() . '.type_contact tc, ' .
				$schema->str() . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and lower(c.value) = :email_lowercase', [
      'email_lowercase' => $email_lowercase,
    ], [
      'email_lowercase' => Types::STRING,
    ]);
	}

	public function get_active_id_by_email(
		string $email,
		Schema $schema
	):int|false
	{
		$email_lowercase = strtolower($email);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema->str() . '.contact c, ' .
				$schema->str() . '.type_contact tc, ' .
				$schema->str() . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and lower(c.value) = :email_lowercase', [
      'email_lowercase' => $email_lowercase,
    ], [
      'email_lowercase' => Types::STRING,
    ]);

		return $id;
	}

	public function count_active_by_name(
    string $name,
    Schema $schema,
  ):int
	{
		$name_lowercase = strtolower($name);

		return $this->db->fetchOne('select count(u.*)
			from ' . $schema->str() . '.users u
			where u.status in (1, 2)
				and lower(u.name) = :name_lowercase', [
      'name_lowercase'  => $name_lowercase,
    ], [
      'name_lowercase'  => Types::STRING,
    ]);
	}

	public function get_active_id_by_name(
    string $name,
    Schema $schema,
  ):int|false
	{
		$name_lowercase = strtolower($name);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema->str() . '.users u
			where u.status in (1, 2)
				and lower(u.name) = :name_lowercase', [
      'name_lowercase'  => $name_lowercase,
    ], [
      'name_lowercase'  => Types::STRING,
    ]);

		return $id;
	}

	public function count_active_by_code(
    string $code,
    Schema $schema,
  ):int
	{
		$code_lowercase = strtolower($code);

		return $this->db->fetchOne('select count(u.*)
			from ' . $schema->str() . '.users u
			where u.status in (1, 2)
				and lower(u.code) = :code_lowercase', [
      'code_lowercase'  => $code_lowercase,
    ], [
      'code_lowercase' => Types::STRING,
    ]);
	}

	public function get_by_typeahead_code(
    string $code,
    Schema $schema,
  ):int|false
	{
		$code_lowercase = strtolower($code);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema->str() . '.users u
			where lower(u.code) = :code_lowercase', [
      'code_lowercase'  => $code_lowercase,
    ], [
      'code_lowercase'  => Types::STRING,
    ]);

		return $id;
	}

	public function get_active_id_by_code(
    string $code,
    Schema $schema,
  ):int|false
	{
		$code_lowercase = strtolower($code);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema->str() . '.users u
			where u.status in (1, 2)
				and lower(u.code) = :code_lowercase', [
      'code_lowercase'  => $code_lowercase,
    ], [
      'code_lowercase'  => Types::STRING,
    ]);

		return $id;
	}

	public function get(
    int $id,
    Schema $schema,
  ):array|false
	{
		$user = $this->db->fetchAssociative('select u.*
			from ' . $schema->str() . '.users u
			where u.id = :id', [
      'id'  => $id,
    ], [
      'id'  => Types::INTEGER,
    ]);

		return $user;
	}

	public function add(
		string $name,
		string $email,
    int $created_by,
		Schema $schema,
	):int
	{
    $this->db->beginTransaction();
    $this->db->insert($schema->str() . '.users', [
      'name'  => $name,
      'created_by', $created_by,
    ], [
      'name'  => Types::STRING,
      'created_by'  => Types::INTEGER,
    ]);

    $user_id = (int) $this->db->lastInsertId($schema->str() . '.users_id_seq');

		$stmt = $this->db->prepare('insert into ' . $schema->str() . '.contact c
			(user_id, value, created_by, is_email, id_type_contact)
			values(:user_id, :email, :created_by, true, (
				select id from ' . $schema->str() . '.type_contact
				where abbrev = \'mail\'
			))');

		$stmt->bindValue('user_id', $user_id, Types::INTEGER);
		$stmt->bindValue('email', $email, Types::STRING);
		$stmt->bindValue('created_by', $created_by, Types::INTEGER);
		$stmt->executeStatement();

    $this->db->commit();

    return $user_id;
	}

	public function set_password(
		int $id,
		string $password,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'password' => $password,
    ], [
      'id' => $id,
    ], [
      'password' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_postcode(
		int $id,
		string $postcode,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'postcode' => $postcode,
    ], [
      'id' => $id,
    ], [
      'postcode' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function del_postcode(
		int $id,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'postcode' => null,
    ], [
      'id' => $id,
    ], [
      'postcode' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function del_image_file(
		int $id,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'image_file' => null,
    ], [
      'id' => $id,
    ], [
      'image_file' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_code(
		int $id,
		string|null $code,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'code' => $code,
    ], [
      'id' => $id,
    ], [
      'code' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_name(
		int $id,
		string $name,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'name' => $name,
    ], [
      'id' => $id,
    ], [
      'name' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_full_name(
		int $id,
		string $full_name,
    string $full_name_access,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'full_name' => $full_name,
      'full_name_access' => $full_name_access,
    ], [
      'id' => $id,
    ], [
      'full_name' => Types::STRING,
      'full_name_access' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_comments(
		int $id,
		string|null $comments,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'comments' => $comments,
    ], [
      'id' => $id,
    ], [
      'comments' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_hobbies(
		int $id,
		string|null $hobbies,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'hobbies' => $hobbies,
    ], [
      'id' => $id,
    ], [
      'hobbies' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_admin_comments(
		int $id,
		string|null $admin_comments,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'admin_comments' => $admin_comments,
    ], [
      'id' => $id,
    ], [
      'admin_comments' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_role(
		int $id,
		string $role,
		Schema $schema,
	):int
	{
    if (isset($role))
    {
      if (!in_array($role, ['admin', 'user']))
      {
        throw new LogicException('wrong role: ' . $role);
      }
    }

		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'role' => $role,
    ], [
      'id' => $id,
    ], [
      'role' => Types::STRING,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

	public function set_is_leaving(
		int $id,
		bool $is_leaving,
		Schema $schema,
	):int
	{
    $status = match($is_leaving){
      true => 2,
      false => 1,
    };
    $affected_rows = (int) $this->db->executeStatement('update ' . $schema->str() . '.users
      set is_leaving = :is_leaving, status = :status
      where id = :id', [
        'is_leaving'  => $is_leaving,
        'status'  => $status,
        'id'  => $id,
      ], [
        'is_leaving'  => Types::BOOLEAN,
        'status'  => Types::INTEGER,
        'id'  => Types::INTEGER,
      ]);
    return $affected_rows;
	}

	public function set_is_active(
		int $id,
		bool $is_active,
		Schema $schema,
	):int
	{
    $status = match($is_active){
      true => 1,
      false => 0,
    };
    $affected_rows = (int) $this->db->executeStatement('update ' . $schema->str() . '.users
      set is_leaving = :is_active, status = :status
      where id = :id', [
        'is_active'  => $is_active,
        'status'  => $status,
        'id'  => $id,
      ], [
        'is_active'  => Types::BOOLEAN,
        'status'  => Types::INTEGER,
        'id'  => Types::INTEGER,
      ]);
    return $affected_rows;
	}

	public function set_periodic_overview_en(
		int $id,
		bool $periodic_overview_en,
		Schema $schema,
	):int
	{
		$affected_rows = (int) $this->db->update($schema->str() . '.users', [
      'periodic_overview_en' => $periodic_overview_en,
    ], [
      'id' => $id,
    ], [
      'periodic_overview_en' => Types::BOOLEAN,
      'id' => Types::INTEGER,
    ]);
    return $affected_rows;
	}

  /**
   * not used yet
   */
	public function register(
    array $user,
    Schema $schema
  ):int
	{
		$this->db->beginTransaction();

		$mobile = $user['mobile'];
		$phone = $user['phone'];
		$email = $user['email'];

		unset($user['mobile'], $user['phone'], $user['email']);

    $this->db->insert($schema->str() . '.users', $user);
    $user_id = (int) $this->db->lastInsertId($schema->str() . '.users_id_seq');

    $tc = [];
		$stmt = $this->db->prepare('select abbrev, id
      from ' . $schema->str() . '.type_contact');
		$res = $stmt->executeQuery();
		while($row = $res->fetchAssociative())
		{
			$tc[$row['abbrev']] = $row['id'];
		}

		$mail = [
			'user_id'			=> $user_id,
			'access'      => 'admin',
			'value'				=> strtolower($email),
			'id_type_contact'	=> $tc['mail'],
		];

    $this->db->insert($schema->str() . '.contact', $mail);

    if (isset($mobile) && $mobile)
		{
			$gsm = [
				'user_id'			=> $user_id,
				'access'            => 'admin',
				'value'				=> $mobile,
				'id_type_contact'	=> $tc['gsm'],
			];

			$this->db->insert($schema->str() . '.contact', $gsm);
		}

		if (isset($phone) && $phone)
		{
			$tel = [
				'user_id'			=> $user_id,
				'access'            => 'admin',
				'value'				=> $phone,
				'id_type_contact'	=> $tc['tel'],
			];

			$this->db->insert($schema->str() . '.contact', $tel);
		}

		$this->db->commit();

		return $user_id;
	}

  /**
   * not used yet
   */
	public function del(
    int $id,
    Schema $schema,
  ):int
	{
    $affected_rows = (int) $this->db->delete($schema->str() . '.users', [
      'id' => $id,
    ], [
      'id'  => Types::INTEGER,
    ]);

		return $affected_rows;
	}

	public function is_active(
    int $id,
    Schema $schema
  ):bool
	{
		return $this->db->fetchOne('select id
			from ' . $schema->str() . '.users
			where status in (1, 2)
				and id = :id', [
      'id'  => $id,
    ], [
      'id'  => Types::INTEGER,
    ]) ? true : false;
	}

  public function set_bulk_full_name_access(
    string $full_name_access,
    array $user_ids,
    Schema $schema,
  ):int
  {
    return (int) $this->db->executeStatement('update ' .
      $schema->str() . '.users
      set full_name_access = :full_name_access
      where id in (:user_ids)', [
        'full_name_access'  => $full_name_access,
        'user_ids'  => $user_ids,
      ], [
        'full_name_access'  => Types::STRING,
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
  }

  public function set_bulk_comments(
    string|null $comments,
    array $user_ids,
    Schema $schema,
  ):int
  {
    if (isset($comments))
    {
      return (int) $this->db->executeStatement('update ' .
        $schema->str() . '.users
        set comments = :comments
        where id in (:user_ids)', [
          'comments'  => $comments,
          'user_ids'  => $user_ids,
        ], [
          'comments'  => Types::STRING,
          'user_ids'  => ArrayParameterType::INTEGER,
        ]);
    }
    return (int) $this->db->executeStatement('update ' .
      $schema->str() . '.users
      set comments = null
      where id in (:user_ids)', [
        'user_ids'  => $user_ids,
      ], [
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
  }

  public function set_bulk_admin_comments(
    string|null $admin_comments,
    array $user_ids,
    Schema $schema,
  ):int
  {
    if (isset($admin_comments))
    {
      return (int) $this->db->executeStatement('update ' .
        $schema->str() . '.users
        set admin_comments = :admin_comments
        where id in (:user_ids)', [
          'admin_comments'  => $admin_comments,
          'user_ids'  => $user_ids,
        ], [
          'admin_comments'  => Types::STRING,
          'user_ids'  => ArrayParameterType::INTEGER,
        ]);
    }
    return (int) $this->db->executeStatement('update ' .
      $schema->str() . '.users
      set admin_comments = null
      where id in (:user_ids)', [
        'user_ids'  => $user_ids,
      ], [
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
  }

  public function set_bulk_role(
    string $role,
    array $user_ids,
    Schema $schema,
  ):int
  {
    return (int) $this->db->executeStatement('update ' .
      $schema->str() . '.users
      set role = :role
      where id in (:user_ids)', [
        'role'  => $role,
        'user_ids'  => $user_ids,
      ], [
        'role'  => Types::STRING,
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
  }

  public function set_bulk_status(
    int $status,
    array $user_ids,
    Schema $schema,
  ):int
  {
    return (int) $this->db->executeStatement('update ' .
      $schema->str() . '.users
      set status = :status
      where id in (:user_ids)', [
        'status'  => $status,
        'user_ids'  => $user_ids,
      ], [
        'status'  => Types::INTEGER,
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
  }

  public function set_bulk_active(
    bool $is_active,
    array $user_ids,
    Schema $schema,
  ):int
  {
    $status = $is_active ? 1 : 0;
    return (int) $this->db->executeStatement('update ' .
      $schema->str() . '.users
      set is_active = :is_active,
      status = :status,
      is_leaving = false
      where id in (:user_ids)', [
        'is_active'  => $is_active,
        'status'  => $status,
        'user_ids'  => $user_ids,
      ], [
        'is_active'  => Types::BOOLEAN,
        'status'  => Types::INTEGER,
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
  }

  public function set_bulk_leaving(
    bool $is_leaving,
    array $user_ids,
    Schema $schema,
  ):int
  {
    $status = $is_leaving ? 2 : 1;
    return (int) $this->db->executeStatement('update ' .
      $schema->str() . '.users
      set is_leaving = :is_leaving,
      status = :status,
      where id in (:user_ids)
        and is_active
        and remote_schema is null
        and remote_email is null', [
        'is_leaving'  => $is_leaving,
        'status'  => $status,
        'user_ids'  => $user_ids,
      ], [
        'is_active'  => Types::BOOLEAN,
        'status'  => Types::INTEGER,
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
  }

  public function set_bulk_periodic_overview_en(
    bool $periodic_overview_en,
    array $user_ids,
    Schema $schema,
  ):int
  {
    return (int) $this->db->executeStatement('update ' .
      $schema->str() . '.users
      set periodic_overview_en = :periodic_overview_en
      where id in (:user_ids)', [
        'periodic_overview_en'  => $periodic_overview_en,
        'user_ids'  => $user_ids,
      ], [
        'periodic_overview_en'  => Types::BOOLEAN,
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);
  }

  public function get_selected(
    array $user_ids,
    Schema $schema,
  ):array
  {
    $users = [];

    $res = $this->db->executeQuery('select u.*
      from ' . $schema->str() . '.users u
      where u.id in (:user_ids)
      order by u.code asc', [
        'user_ids'  => $user_ids,
      ], [
        'user_ids'  => ArrayParameterType::INTEGER,
      ]);

    while($row = $res->fetchAssociative())
    {
      $users[$row['id']] = $row;
    }

    return $users;
  }

  public function get_selected_min_limit(
    array $account_ids,
    Schema $schema,
  ):array
  {
    $accounts = [];

    $res = $this->db->executeQuery('select u.id,
      u.code, u.name,
      min_limit.min_limit
			from ' . $schema->str() . '.users u
      left join lateral (
        select minl.min_limit
        from ' . $schema->str() . '.min_limit minl
        where minl.account_id = u.id
        order by minl.created_at desc
        limit 1
      ) min_limit on true
      where u.id in (:account_ids)', [
        'account_ids' => $account_ids,
      ], [
        'account_ids' => ArrayParameterType::INTEGER,
      ]
    );

    while ($row = $res->fetchAssociative())
    {
      $accounts[$row['id']] = $row;
    }

		return $accounts;
  }

  public function get_selected_max_limit(
    array $account_ids,
    Schema $schema,
  ):array
  {
    $accounts = [];

    $res = $this->db->executeQuery('select u.id,
      u.code, u.name,
      max_limit.max_limit
			from ' . $schema->str() . '.users u
      left join lateral (
        select maxl.max_limit
        from ' . $schema->str() . '.max_limit maxl
        where maxl.account_id = u.id
        order by maxl.created_at desc
        limit 1
      ) max_limit on true
      where u.id in (:account_ids)', [
        'account_ids' => $account_ids,
      ], [
        'account_ids' => ArrayParameterType::INTEGER,
      ]
    );

    while ($row = $res->fetchAssociative())
    {
      $accounts[$row['id']] = $row;
    }

		return $accounts;
  }

  public function get_all_by_status(
    string $status,
    Schema $schema,
  ):array
  {
		$sql_where = match($status){
      'all' => '1 = 1',
      'active' => 'status in (1, 2)',
      'leaving' => 'status = 2',
      'new' => 'status = 1 and u.adate > :activated_at',
      'inactive'  => 'status = 0',
      'ip'  => 'status = 5',
      'im'  => 'status = 6',
      'extern'  => 'status = 7',
    };

    $sql_params = [];
    $sql_types = [];

    if  ($status === 'new')
    {
      $new_user_treshold = $this->config_service->get_new_user_treshold(
        schema: $schema
      );
      $sql_params['activated_at'] = $new_user_treshold;
      $sql_types['activated_at'] = Types::DATETIME_IMMUTABLE;
    }

    $users = [];

    $query = 'select u.*
      from ' . $schema->str() . '.users u
      where ' . $sql_where . '
      order by u.code asc';

    $res = $this->db->executeQuery($query, $sql_params, $sql_types);

    while($row = $res->fetchAssociative())
    {
      $users[$row['id']] = $row;
    }

    return $users;
  }

  public function get_contacts_ary(
    Schema $schema
  ):array
  {
    $ary = [];
    $query = 'select tc.abbrev,
        c.user_id, c.value, c.access
      from ' . $schema->str() . '.contact c, ' .
        $schema->str() . '.type_contact tc, ' .
        $schema->str() . '.users u
      where tc.id = c.id_type_contact
      and c.user_id = u.id';
    $res = $this->db->executeQuery($query);

    while ($row = $res->fetchAssociative())
    {
      $ary[$row['user_id']][$row['abbrev']][] = [
        'value'         => $row['value'],
        'access'        => $row['access'],
      ];
    }

    return $ary;
  }

  public function get_address(
    int $user_id,
    Schema $schema,
  ):string|false
  {
    return $this->db->fetchOne('select c.value
      from ' . $schema->str() . '.contact c, ' .
        $schema->str() . '.type_contact tc
      where c.user_id = :user_id
        and c.id_type_contact = tc.id
        and tc.abbrev = \'adr\'
      limit 1', [
        'user_id' => $user_id,
      ], [
        'user_id' => Types::INTEGER,
      ]);
  }

  public function get_all_active_with_addresses(
    Schema $schema,
  ):array
  {
    $ary = [];
    $stmt = $this->db->prepare('select
      u.id as user_id, u.name, u.code,
      c.value, c.access
      from ' . $schema->str() . '.users u
      left join ' . $schema->str() . '.contact c
       on c.user_id = u.id
        and c.id_type_contact = (select tc.id
        from ' . $schema->str() . '.type_contact tc
        where tc.abbrev = \'adr\')
      where status in (1, 2)
      order by u.code asc');
    $res = $stmt->executeQuery();
    while ($row = $res->fetchAssociative())
    {
      $ary[$row['user_id']][] = $row;
    }
    return $ary;
  }

  public function get_with_page_data(
    int $id,
    string|null $status,
    Schema $schema,
  ):array|false
  {
		$sql_where = match($status){
      'all' => '1 = 1',
      'active' => '%table%.status in (1, 2)',
      'leaving' => '%table%.status = 2',
      'new' => '%table%.status = 1 and %table%.adate > :activated_at',
      'inactive'  => '%table%.status = 0',
      'ip'  => '%table%.status = 5',
      'im'  => '%table%.status = 6',
      'extern'  => '%table%.status = 7',
    };

    $sql_where_pu = strtr($sql_where, [
      '%table%' => 'pu'
    ]);
    $sql_where_nu = strtr($sql_where, [
      '%table%' => 'nu'
    ]);

    $sql_params = [
      'id'  => $id,
    ];
    $sql_types = [
      'id'  => Types::INTEGER,
    ];

    if  ($status === 'new')
    {
      $new_user_treshold = $this->config_service->get_new_user_treshold(
        schema: $schema
      );
      $sql_params['activated_at'] = $new_user_treshold;
      $sql_types['activated_at'] = Types::DATETIME_IMMUTABLE;
    }

    $data = $this->db->fetchAssociative('select u.*,
      coalesce(cd.contacts, \'[]\'::jsonb) as contacts,
      coalesce(msg.count, 0) as message_count,
      coalesce(trns.count, 0) as transaction_count,
      login.max as last_login,
      min_limit.min_limit,
      max_limit.max_limit,
      coalesce(balance.balance, 0) as balance,
      coalesce(tags.tags, \'[]\'::jsonb) as tags,
      nav.prev_id,
      nav.next_id
			from ' . $schema->str() . '.users u
      left join lateral (
        select
          (select pu.id
            from ' . $schema->str() . '.users pu
            where pu.code < u.code
              and ' . $sql_where_pu . '
            order by pu.code desc
            limit 1
          ) as prev_id,
          (select nu.id
            from ' . $schema->str() . '.users nu
            where nu.code > u.code
              and ' . $sql_where_nu . '
            order by nu.code asc
            limit 1
          ) as next_id
      ) nav on true
      left join lateral (
        select jsonb_agg(
          jsonb_build_object(
            \'value\', c.value,
            \'comments\', c.comments,
            \'abbrev\', tc.abbrev,
            \'name\', tc.name
          )
        ) as contacts
        from ' . $schema->str() . '.contact c
        join ' . $schema->str() . '.type_contact tc
          on c.id_type_contact = tc.id
        where c.user_id = u.id
      ) cd on true
      left join lateral (
        select count(m.*)
        from ' . $schema->str() . '.messages m
        where m.user_id = u.id
      ) msg on true
      left join lateral (
        select count(t.*)
        from ' . $schema->str() . '.transactions t
        where t.id_to = u.id or t.id_from = u.id
      ) trns on true
      left join lateral (
        select max(l.created_at)
        from ' . $schema->str() . '.login l
        where l.user_id = u.id
      ) login on true
      left join lateral (
        select minl.min_limit
        from ' . $schema->str() . '.min_limit minl
        where minl.account_id = u.id
        order by minl.created_at desc
        limit 1
      ) min_limit on true
      left join lateral (
        select maxl.max_limit
        from ' . $schema->str() . '.max_limit maxl
        where maxl.account_id = u.id
        order by maxl.created_at desc
        limit 1
      ) max_limit on true
      left join lateral (
        select bal.balance
        from ' . $schema->str() . '.balance bal
        where bal.account_id = u.id
        order by bal.created_at desc
        limit 1
      ) balance on true
      left join lateral (
        select jsonb_agg(
          jsonb_build_object(
            \'txt\', tg.txt,
            \'description\', tg.description,
            \'id\', tg.id
          )
        ) as tags
        from ' . $schema->str() . '.tags tg
        join ' . $schema->str() . '.users_tags ut
          on ut.tag_id = tg.id
        where ut.user_id = u.id
        group by tg.id
        order by tg.pos asc
      ) tags on true
      where u.id = :id',
      $sql_params,
      $sql_types
    );

    if ($data !== false)
    {
      $data['contacts'] = json_decode($data['contacts'], true);
      $data['tags'] = json_decode($data['tags'], true);
    }

		return $data;
  }

	public function is_unique_code(
		string $code,
		null|int $except_id,
		Schema $schema
	):bool
	{
		if ($code === '')
		{
			throw new LogicException('Code can not be empty string.');
		}

		$lower_code = strtolower($code);

		$query = 'select id
			from ' . $schema->str() . '.users
			where code is not null
				and lower(code) = :lower_code';

		if (isset($except_id))
		{
			$query .= ' and id <> :except_id';
		}

		$stmt = $this->db->prepare($query);

		$stmt->bindValue('lower_code', $lower_code, Types::STRING);

		if (isset($except_id))
		{
			$stmt->bindValue('except_id', $except_id, Types::INTEGER);
		}

		$res = $stmt->executeQuery();
		$id = $res->fetchOne();

		if ($id === false)
		{
			return true;
		}

		return false;
	}

	public function is_unique_name(
		string $name,
		null|int $except_id,
		Schema $schema
	):bool
	{
		if ($name === '')
		{
			throw new LogicException('Name can not be empty string.');
		}

		$lower_name = strtolower($name);

		$query = 'select id
			from ' . $schema->str() . '.users
			where name is not null
				and lower(name) = :lower_name';

		if (isset($except_id))
		{
			$query .= ' and id <> :except_id';
		}

		$stmt = $this->db->prepare($query);

		$stmt->bindValue('lower_name', $lower_name, \PDO::PARAM_STR);

		if (isset($except_id))
		{
			$stmt->bindValue('except_id', $except_id, \PDO::PARAM_INT);
		}

		$res = $stmt->executeQuery();
		$id = $res->fetchOne();

		if ($id === false)
		{
			return true;
		}

		return false;
	}
}
