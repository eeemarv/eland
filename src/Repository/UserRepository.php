<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\Xdb;
use Doctrine\DBAL\Connection as Db;
use Predis\Client as Redis;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Filter\UserFilter;
use App\Filter\FilterQuery;
use App\Util\Sort;
use App\Util\Pagination;

class UserRepository
{
	private $db;
	private $xdb;
	private $redis;
	private $ttl = 2592000;
	private $redisPrefix = 'user_cache_';
	private $isCli;

	public function __construct(Db $db, Xdb $xdb, Redis $redis)
	{
		$this->db = $db;
		$this->xdb = $xdb;
		$this->redis = $redis;
		$this->isCli = php_sapi_name() === 'cli' ? true : false;
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

	public function clear(int $id, string $schema)
	{
		$redisKey = $this->redisPrefix . $schema . '_' . $id;

		$this->redis->del($redisKey);
		unset($this->local[$schema][$id]);

		return;
	}

	public function get(int $id, string $schema):array
	{
		$redisKey = $this->redisPrefix . $schema . '_' . $id;

		if (isset($this->local[$schema][$id]))
		{
			return $this->local[$schema][$id];
		}

		if ($this->redis->exists($redisKey))
		{
			$user = unserialize($this->redis->get($redisKey));

			if (!$this->isCli)
			{
				$this->local[$schema][$id] = $user;
			}

			return $user;
		}

		$user = $this->readFromDb($id, $schema);

		if (isset($user))
		{
			$this->redis->set($redisKey, serialize($user));
			$this->redis->expire($redisKey, $this->ttl);
		
			if (!$this->isCli)
			{
				$this->local[$schema][$id] = $user;
			}
		}

		return $user;
	}

	public function getByEmail($email, $schema):array
	{
		$id = $this->db->fetchColumn('select c.id_user
			from ' . $schema . '.contact c,
				' . $schema . '.type_contact tc
			where c. value = ?
				and tc.id = c.id_type_contact
				and tc.abbrev = \'mail\'', [$email]);

		if (!isset($id))
		{
			return [];
		}

		return $this->get($id, $schema);
	}

	private function readFromDb(int $id, string $schema)
	{
		$user = $this->db->fetchAssoc('select * from ' . $schema . '.users where id = ?', [$id]);

		if (!is_array($user))
		{
			return [];
		}

		// hack eLAS compatibility (in eLAND limits can be null)
		$user['minlimit'] = $user['minlimit'] === -999999999 ? null : $user['minlimit'];
		$user['maxlimit'] = $user['maxlimit'] === 999999999 ? null : $user['maxlimit'];

		$row = $this->xdb->get('user_fullname_access', $id, $schema);

		if ($row)
		{
			$user += ['fullname_access' => $row['data']['fullname_access']];
		}
		else
		{
			$user += ['fullname_access' => 'admin'];
			$this->xdb->set('user_fullname_access', $id, $schema, ['fullname_access' => 'admin']);
		}

		if ($user['accountrole'] === 'interlets')
		{

		}

		return $user;
	}

	/**
	 * for periodic process for when cache gets out sync
	 */
	public function sync(int $id, string $schema)
	{
		$user = $this->read_from_db($id, $schema);

		if (!count($user))
		{
			return;
		}

		$user = serialize($user);

		$redisKey = $this->redisPrefix . $schema . '_' . $id;

		if ($this->redis->exists($redisKey))
		{
			if ($this->redis->get($redisKey) === $user)
			{
				return;
			}
		}

		$this->redis->set($redisKey, $user);
		$this->redis->expire($redisKey, $this->ttl);
		unset($this->local[$schema][$id]);
	}
}
