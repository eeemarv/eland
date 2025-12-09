<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\AddressAry;
use App\DTO\Schema;
use App\Service\ConfigService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Service\UserCacheService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Mime\Address;

class UserRepository
{
	public function __construct(
		private readonly Db $db,
		private readonly UserCacheService $user_cache_service,
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
  ):string
	{
    $account_str = $this->db->fetchOne('select trim(concat(coalesce(code,\'\'), \' \', coalesce(name, \'\')))
            from ' . $schema->str() . '.users
			where id = ?',
			[$id],
			[Types::INTEGER]);

		if (!$account_str)
		{
			throw new NotFoundHttpException('User with id ' . $id . ' not found.');
		}

		return $account_str;
	}

	public function insert_login(
		int $user_id,
		string $agent,
		string $ip,
		Schema $schema
	):void
	{
		$this->db->insert($schema->str() . '.login', [
			'user_id'       => $user_id,
			'agent'         => $agent,
			'ip'            => $ip,
		]);
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
				and lower(c.value) = ?',
				[$email_lowercase],
				[Types::STRING]);
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
				and lower(c.value) = ?',
				[$email_lowercase],
				[Types::STRING]);
	}

	public function get_active_id_by_email(
		string $email,
		Schema $schema
	):int
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
				and lower(c.value) = ?',
				[$email_lowercase],
				[Types::STRING]);

		if (!$id)
		{
			throw new NotFoundHttpException('User with email ' . $email . ' not found.');
		}

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
				and lower(u.name) = ?',
				[$name_lowercase],
				[Types::STRING]
			);
	}

	public function get_active_id_by_name(
    string $name,
    Schema $schema,
  ):int
	{
		$name_lowercase = strtolower($name);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema->str() . '.users u
			where u.status in (1, 2)
				and lower(u.name) = ?',
				[$name_lowercase],
				[Types::STRING]
			);

		if (!$id)
		{
			throw new NotFoundHttpException('User with name ' . $name . ' not found.');
		}

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
				and lower(u.code) = ?',
				[$code_lowercase],
				[Types::STRING]
			);
	}


	public function get_by_typeahead_code(
    string $code,
    Schema $schema,
  ):int
	{
		$code_lowercase = strtolower($code);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema->str() . '.users u
			where lower(u.code) = ?',
			[$code_lowercase],
			[Types::STRING]
		);

		if (!$id)
		{
			return 0;
		}

		return $id;
	}

	public function get_active_id_by_code(
    string $code,
    Schema $schema,
  ):int
	{
		$code_lowercase = strtolower($code);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema->str() . '.users u
			where u.status in (1, 2)
				and lower(u.code) = ?',
				[$code_lowercase],
				[Types::STRING]
			);

		if (!$id)
		{
			throw new NotFoundHttpException('User with code ' . $code . ' not found.');
		}

		return $id;
	}

	public function get(
    int $id,
    Schema $schema,
  ):array
	{
		$user = $this->db->fetchAssociative('select u.*
			from ' . $schema->str() . '.users u
			where u.id = ?',
			[$id],
			[Types::INTEGER]
		);

		if (!$user)
		{
			throw new NotFoundHttpException('User with id ' . $id . ' not found');
		}

		return $user;
	}

	public function set_password(
		int $id,
		string $password,
		Schema $schema,
	):void
	{
		$this->db->update($schema->str() . '.users',
			['password' => $password],
			['id' => $id],
			['password' => Types::STRING, 'id' => Types::INTEGER]
		);
		$this->user_cache_service->clear($id, $schema->str());
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
  ):bool
	{
    // change to on delete cascade in db
    $this->db->delete($schema->str() . '.contact',
      ['user_id' => $id]);
    $success = $this->db->delete($schema->str() . '.users',
      ['id' => $id]) ? true : false;
		if ($success)
		{
      $this->user_cache_service->clear($id, $schema->str());
		}

		return $success;
	}

	public function is_active(
    int $id,
    Schema $schema
  ):bool
	{
		return $this->db->fetchOne('select id
			from ' . $schema->str() . '.users
			where status in (1, 2)
				and id = ?', [$id], [Types::INTEGER]) ? true : false;
	}

  public function set_bulk_full_name_access(
    string $full_name_access,
    array $user_ids,
    Schema $schema,
  ):void
  {
    $this->db->executeStatement('update ' .
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
  ):void
  {
    if (isset($comments))
    {
      $this->db->executeStatement('update ' .
        $schema->str() . '.users
        set comments = :comments
        where id in (:user_ids)', [
          'comments'  => $comments,
          'user_ids'  => $user_ids,
        ], [
          'comments'  => Types::STRING,
          'user_ids'  => ArrayParameterType::INTEGER,
        ]);
      return;
    }
    $this->db->executeStatement('update ' .
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
  ):void
  {
    if (isset($admin_comments))
    {
      $this->db->executeStatement('update ' .
        $schema->str() . '.users
        set admin_comments = :admin_comments
        where id in (:user_ids)', [
          'admin_comments'  => $admin_comments,
          'user_ids'  => $user_ids,
        ], [
          'admin_comments'  => Types::STRING,
          'user_ids'  => ArrayParameterType::INTEGER,
        ]);
      return;
    }
    $this->db->executeStatement('update ' .
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
  ):void
  {
    $this->db->executeStatement('update ' .
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
  ):void
  {
    $this->db->executeStatement('update ' .
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

  public function set_bulk_periodic_overview_en(
    bool $periodic_overview_en,
    array $user_ids,
    Schema $schema,
  ):void
  {
    $this->db->executeStatement('update ' .
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

  public function get_all_by_status(
    string $status,
    Schema $schema,
  ):array
  {
    $sql_where = '1 = 1';
    $sql_params = [];
    $sql_types = [];

    switch ($status)
    {
      case 'all':
        break;
      case 'active':
        $sql_where = 'u.status in (1, 2)';
        break;
      case 'new':
        $new_user_treshold = $this->config_service->get_new_user_treshold(schema: $schema);
        $sql_where = 'u.status = 1 and u.adate > :activated_at';
        $sql_params['activated_at'] = $new_user_treshold;
        $sql_types['activated_at'] = Types::DATETIME_IMMUTABLE;
        break;
      case 'leaving':
        $sql_where = 'u.status = 2';
        break;
      case 'inactive':
        $sql_where = 'u.status = 0';
        break;
      case 'ip':
        $sql_where = 'u.status = 5';
        break;
      case 'im':
        $sql_where = 'u.status = 6';
        break;
      case 'extern':
        $sql_where = 'u.status = 7';
        break;
      default:
        throw new \Exception('wrong value for status: ' . $status);
        break;
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

  public function get_last_login_ary(
    Schema $schema,
  ):array
  {
    $ary = [];

    $res = $this->db->executeQuery('select user_id, max(created_at) as last_login
      from ' . $schema->str() . '.login
      group by user_id');

    while ($row = $res->fetchAssociative())
    {
      $ary[$row['user_id']] = $row['last_login'];
    }

    return $ary;
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
      limit 1',
          ['user_id' => $user_id],
          ['user_id' => Types::INTEGER]);
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
}
