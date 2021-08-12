<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Filter\FilterQuery;
use App\Service\UserCacheService;
use App\Util\Sort;
use App\Util\Pagination;

class UserRepository
{
	public function __construct(
		protected Db $db,
		protected UserCacheService $user_cache_service
	)
	{
	}

	public function get_account_str(int $id, string $schema):string
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
				and lower(c.value) = ?', [$email_lowercase], [\PDO::PARAM_STR]);
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
				and lower(c.value) = ?', [$email_lowercase], [\PDO::PARAM_STR]);
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
			['password' => \PDO::PARAM_STR]
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
		$rs = $this->db->prepare('select abbrev, id
            from ' . $schema . '.type_contact');
		$rs->execute();

		while($row = $rs->fetch())
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

	/********************* */
	/*
	public function getFiltered(string $schema, FilterQuery $filterQuery, Sort $sort, Pagination $pagination):array
	{
		$query = 'select u.* from ' . $schema . '.users u';
		$query .= $filterQuery->getWhereQueryString();
		$query .= $sort->query();
		$query .= $pagination->query();

		$users = [];

		$rs = $this->db->executeQuery($query, $filterQuery->getParams());

		while ($row = $rs->fetch())
		{
			$users[] = $row;
		}

		return $users;
	}

	public function getFilteredRowCount(string $schema, FilterQuery $filterQuery):int
	{
		$query = 'select count(u.*) from ' . $schema . '.users u' . $filterQuery->getWhereQueryString();
		return $this->db->fetchOne($query, $filterQuery->getParams());
	}

	public function getFilteredBalanceSum(string $schema, FilterQuery $filterQuery):int
	{
		$query = 'select sum(u.saldo) from ' . $schema . '.users u' . $filterQuery->getWhereQueryString();
		return $this->db->fetchOne($query, $filterQuery->getParams()) ?? 0;
	}
	*/
}
