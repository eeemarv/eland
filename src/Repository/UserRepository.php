<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Filter\UserFilter;
use App\Filter\FilterQuery;
use App\Service\UserCacheService;
use App\Util\Sort;
use App\Util\Pagination;

class UserRepository
{
	protected Db $db;
	protected UserCacheService $user_cache_service;

	public function __construct(
		Db $db,
		UserCacheService $user_cache_service
	)
	{
		$this->db = $db;
		$this->user_cache_service = $user_cache_service;
	}

	public function count_active_by_email(
		string $email,
		string $schema
	):int
	{
		$email_lowercase = strtolower($email);

		return $this->db->fetchColumn('select count(c.*)
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc, ' .
				$schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and lower(c.value) = ?', [$email_lowercase]);
	}

	public function get_active_id_by_eamil(
		string $email,
		string $schema
	):int
	{
		$email_lowercase = strtolower($email);

		$id = $this->db->fetchColumn('select u.id
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc, ' .
				$schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.user_id = u.id
				and u.status in (1, 2)
				and lower(c.value) = ?', [$email_lowercase]);

		if (!$id)
		{
			throw new NotFoundHttpException('User with email ' . $email . ' not found.');
		}

		return $id;
	}

	public function count_active_by_name(string $name, string $schema):int
	{
		$name_lowercase = strtolower($name);

		return $this->db->fetchColumn('select count(u.*)
			from ' . $schema . '.users u
			where u.status in (1, 2)
				and lower(u.name) = ?', [$name_lowercase]);
	}

	public function get_active_id_by_name(string $name, string $schema):int
	{
		$name_lowercase = strtolower($name);

		$id = $this->db->fetchColumn('select u.id
			from ' . $schema . '.users u
			where u.status in (1, 2)
				and lower(u.name) = ?', [$name_lowercase]);

		if (!$id)
		{
			throw new NotFoundHttpException('User with name ' . $name . ' not found.');
		}

		return $id;
	}

	public function count_active_by_code(string $code, string $schema):int
	{
		$code_lowercase = strtolower($code);

		return $this->db->fetchColumn('select count(u.*)
			from ' . $schema . '.users u
			where u.status in (1, 2)
				and lower(u.code) = ?', [$code_lowercase]);
	}

	public function get_active_id_by_code(string $code, string $schema):int
	{
		$code_lowercase = strtolower($code);

		$id = $this->db->fetchColumn('select u.id
			from ' . $schema . '.users u
			where u.status in (1, 2)
				and lower(u.code) = ?', [$code_lowercase]);

		if (!$id)
		{
			throw new NotFoundHttpException('User with code ' . $code . ' not found.');
		}

		return $id;
	}

	public function get(int $id, string $schema):array
	{
		$user = $this->db->fetchAssoc('select u.*
			from ' . $schema . '.users u
			where u.id = ?', [$id]);

		if (!$user)
		{
			throw new NotFoundHttpException('User with id ' . $id . ' not found');
		}

		return $user;
	}

	public function set_password(int $id, string $password, string $schema):void
	{
		$this->db->update($schema . '.users',
			['password' => $password],
			['id' => $id]);
		$this->user_cache_service->clear($id, $schema);
	}

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
		return $this->db->fetchColumn($query, $filterQuery->getParams());
	}

	public function getFilteredBalanceSum(string $schema, FilterQuery $filterQuery):int
	{
		$query = 'select sum(u.saldo) from ' . $schema . '.users u' . $filterQuery->getWhereQueryString();
		return $this->db->fetchColumn($query, $filterQuery->getParams()) ?? 0;
	}
}
