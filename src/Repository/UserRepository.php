<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\AddressAry;
use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Service\UserCacheService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Mime\Address;

class UserRepository
{
	public function __construct(
		protected Db $db,
		protected UserCacheService $user_cache_service
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
    string $schema,
  ):string
	{
    $account_str = $this->db->fetchOne('select trim(concat(coalesce(code,\'\'), \' \', coalesce(name, \'\')))
            from ' . $schema . '.users
			where id = ?',
			[$id],
			[\PDO::PARAM_INT]);

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
		string $schema
	):void
	{
		$this->db->insert($schema . '.login', [
			'user_id'       => $user_id,
			'agent'         => $agent,
			'ip'            => $ip,
		]);
	}

	public function count_email(
		string $email,
		string $schema
	):int
	{
		$email_lowercase = strtolower($email);

		return $this->db->fetchOne('select count(c.*)
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and lower(c.value) = ?',
				[$email_lowercase],
				[\PDO::PARAM_STR]);
	}

	public function count_active_by_email(
		string $email,
		string $schema
	):int
	{
		$email_lowercase = strtolower($email);

		return $this->db->fetchOne('select count(c.*)
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc, ' .
				$schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and lower(c.value) = ?',
				[$email_lowercase],
				[\PDO::PARAM_STR]);
	}

	public function get_active_id_by_email(
		string $email,
		string $schema
	):int
	{
		$email_lowercase = strtolower($email);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc, ' .
				$schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and lower(c.value) = ?',
				[$email_lowercase],
				[\PDO::PARAM_STR]);

		if (!$id)
		{
			throw new NotFoundHttpException('User with email ' . $email . ' not found.');
		}

		return $id;
	}

	public function count_by_name(
		string $name,
		string $schema
	):int
	{
		$name_lowercase = strtolower($name);

		return $this->db->fetchOne('select count(u.*)
                from ' . $schema . '.users u
                where lower(u.name) = ?',
				[$name_lowercase],
				[\PDO::PARAM_STR]
			);
	}

	public function count_active_by_name(string $name, string $schema):int
	{
		$name_lowercase = strtolower($name);

		return $this->db->fetchOne('select count(u.*)
			from ' . $schema . '.users u
			where u.status in (1, 2)
				and lower(u.name) = ?',
				[$name_lowercase],
				[\PDO::PARAM_STR]
			);
	}

	public function get_active_id_by_name(string $name, string $schema):int
	{
		$name_lowercase = strtolower($name);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema . '.users u
			where u.status in (1, 2)
				and lower(u.name) = ?',
				[$name_lowercase],
				[\PDO::PARAM_STR]
			);

		if (!$id)
		{
			throw new NotFoundHttpException('User with name ' . $name . ' not found.');
		}

		return $id;
	}

	public function count_active_by_code(string $code, string $schema):int
	{
		$code_lowercase = strtolower($code);

		return $this->db->fetchOne('select count(u.*)
			from ' . $schema . '.users u
			where u.status in (1, 2)
				and lower(u.code) = ?',
				[$code_lowercase],
				[\PDO::PARAM_STR]
			);
	}


	public function get_by_typeahead_code(string $code, string $schema):int
	{
		$code_lowercase = strtolower($code);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema . '.users u
			where lower(u.code) = ?',
			[$code_lowercase],
			[\PDO::PARAM_STR]
		);

		if (!$id)
		{
			return 0;
		}

		return $id;
	}

	public function get_active_id_by_code(string $code, string $schema):int
	{
		$code_lowercase = strtolower($code);

		$id = $this->db->fetchOne('select u.id
			from ' . $schema . '.users u
			where u.status in (1, 2)
				and lower(u.code) = ?',
				[$code_lowercase],
				[\PDO::PARAM_STR]
			);

		if (!$id)
		{
			throw new NotFoundHttpException('User with code ' . $code . ' not found.');
		}

		return $id;
	}

	public function get(int $id, string $schema):array
	{
		$user = $this->db->fetchAssociative('select u.*
			from ' . $schema . '.users u
			where u.id = ?',
			[$id],
			[\PDO::PARAM_INT]
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
		string $schema
	):void
	{
		$this->db->update($schema . '.users',
			['password' => $password],
			['id' => $id],
			['password' => Types::STRING, 'id' => Types::INTEGER]
		);
		$this->user_cache_service->clear($id, $schema);
	}

	public function register(array $user, string $schema):int
	{
		$this->db->beginTransaction();

		$mobile = $user['mobile'];
		$phone = $user['phone'];
		$email = $user['email'];

		unset($user['mobile'], $user['phone'], $user['email']);

        $this->db->insert($schema . '.users', $user);
        $user_id = (int) $this->db->lastInsertId($schema . '.users_id_seq');

        $tc = [];
		$stmt = $this->db->prepare('select abbrev, id
            from ' . $schema . '.type_contact');

		$res = $stmt->executeQuery();

		while($row = $res->fetchAssociative())
		{
			$tc[$row['abbrev']] = $row['id'];
		}

		$mail = [
			'user_id'			=> $user_id,
			'access'            => 'admin',
			'value'				=> strtolower($email),
			'id_type_contact'	=> $tc['mail'],
		];

        $this->db->insert($schema . '.contact', $mail);

        if (isset($mobile) && $mobile)
		{
			$gsm = [
				'user_id'			=> $user_id,
				'access'            => 'admin',
				'value'				=> $mobile,
				'id_type_contact'	=> $tc['gsm'],
			];

			$this->db->insert($schema . '.contact', $gsm);
		}

		if (isset($phone) && $phone)
		{
			$tel = [
				'user_id'			=> $user_id,
				'access'            => 'admin',
				'value'				=> $phone,
				'id_type_contact'	=> $tc['tel'],
			];

			$this->db->insert($schema . '.contact', $tel);
		}

		$this->db->commit();

		return $user_id;
	}

	public function del(int $id, string $schema):bool
	{
    $this->db->delete($schema . '.contact',
      ['user_id' => $id]);
    $success = $this->db->delete($schema . '.users',
      ['id' => $id]) ? true : false;
		if ($success)
		{
      $this->user_cache_service->clear($id, $schema);
		}

		return $success;
	}

	public function is_active(int $id, string $schema):bool
	{
		return $this->db->fetchOne('select id
			from ' . $schema . '.users
			where status in (1, 2)
				and id = ?', [$id], [\PDO::PARAM_INT]) ? true : false;
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
}
