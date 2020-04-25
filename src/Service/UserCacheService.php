<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;

class UserCacheService
{
	const TTL = 2592000;

	protected Db $db;
	protected Predis $predis;
	protected bool $is_cli;

	protected array $local = [];

	public function __construct(
		Db $db,
		Predis $predis
	)
	{
		$this->db = $db;
		$this->predis = $predis;

		$this->is_cli = php_sapi_name() === 'cli' ? true : false;
	}

	public function clear(int $id, string $schema):void
	{
		if (!$id)
		{
			return;
		}

		$redis_key = $schema . '_user_' . $id;

		$this->predis->del($redis_key);
		unset($this->local[$schema][$id]);

		return;
	}

	public function is_active_user(int $id, string $schema):bool
	{
		$user = $this->get($id, $schema);
		return in_array($user['status'], [1, 2]);
	}

	public function get_role(int $id, string $schema):string
	{
		return $this->get($id, $schema)['accountrole'];
	}

	public function get(int $id, string $schema):array
	{
		$user = $this->temp_get($id, $schema);
		$user['code'] ??= $user['letscode'];

		return $user;
	}

	public function temp_get(int $id, string $schema):array
	{
		if (!$id)
		{
			return [];
		}

		$redis_key = $schema . '_user_' . $id;

		if (isset($this->local[$schema][$id]) && !$this->is_cli)
		{
			return $this->local[$schema][$id];
		}

		if ($this->predis->exists($redis_key))
		{
			return $this->local[$schema][$id] = unserialize($this->predis->get($redis_key));
		}

		$user = $this->read_from_db($id, $schema);

		if (isset($user))
		{
			$this->predis->set($redis_key, serialize($user));
			$this->predis->expire($redis_key, self::TTL);
			$this->local[$schema][$id] = $user;
		}

		return $user;
	}

	protected function read_from_db(int $id, string $schema):array
	{
		$user = $this->db->fetchAssoc('select *
			from ' . $schema . '.users
			where id = ?', [$id]);

		if (!is_array($user))
		{
			return [];
		}

		return $user;
	}

	/**
	 * for periodic process for when cache gets out sync
	 */
	public function sync(int $id, string $schema):void
	{
		$user = $this->read_from_db($id, $schema);

		if (!count($user))
		{
			return;
		}

		$user = serialize($user);

		$redis_key = $schema . '_user_' . $id;

		if ($this->predis->exists($redis_key))
		{
			if ($this->predis->get($redis_key) === $user)
			{
				return;
			}
		}

		$this->predis->set($redis_key, $user);
		$this->predis->expire($redis_key, self::TTL);
		unset($this->local[$schema][$id]);
	}
}
