<?php declare(strict_types=1);

namespace App\Service;

use Redis;
use Doctrine\DBAL\Connection as Db;

class UserCacheService
{
	const KEY_PREFIX = 'users_';
	const TTL = 2592000;

	protected bool $is_cli;
	protected array $local = [];

	public function __construct(
		protected Db $db,
		protected Redis $predis
	)
	{
		$this->is_cli = php_sapi_name() === 'cli';
	}

	public function clear(int $id, string $schema):void
	{
		if (!$id)
		{
			return;
		}

		$redis_key = self::KEY_PREFIX . $schema;
		$this->predis->hdel($redis_key, (string) $id);

		unset($this->local[$schema][$id]);

		return;
	}

	public function clear_all(string $schema):void
	{
		$redis_key = self::KEY_PREFIX . $schema;
		$this->predis->del($redis_key);

		unset($this->local[$schema]);

		return;
	}

	public function is_active_user(int $id, string $schema):bool
	{
		$user = $this->get($id, $schema);
		return in_array($user['status'], [1, 2]);
	}

	public function get_role(int $id, string $schema):string
	{
		return $this->get($id, $schema)['role'];
	}

	public function get(int $id, string $schema):array
	{
		if (!$id)
		{
			return [];
		}

		if (isset($this->local[$schema][$id]) && !$this->is_cli)
		{
			return $this->local[$schema][$id];
		}

		$redis_key = self::KEY_PREFIX . $schema;
		$redis_field = (string) $id;

		if ($this->predis->hexists($redis_key, $redis_field))
		{
			return $this->local[$schema][$id] = json_decode($this->predis->hget($redis_key, $redis_field), true);
		}

		$user = $this->read_from_db($id, $schema);

		if (count($user))
		{
			$this->predis->hset($redis_key, $redis_field, json_encode($user));
			$this->predis->expire($redis_key, self::TTL);
			$this->local[$schema][$id] = $user;
		}

		return $user;
	}

	protected function read_from_db(int $id, string $schema):array
	{
		$user = $this->db->fetchAssociative('select u.*,
				case when mp.id is null
					then \'f\'::bool
					else \'t\'::bool
					end has_open_mollie_payment
			from ' . $schema . '.users u
			left join ' . $schema . '.mollie_payments mp
				on (u.id = mp.user_id
					and not mp.is_paid
					and not mp.is_canceled)
			where u.id = ?', [$id], [\PDO::PARAM_INT]);

		if ($user === false)
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

		$user = json_encode($user);

		$redis_key = self::KEY_PREFIX . $schema;
		$redis_field = (string) $id;

		if ($this->predis->hexists($redis_key, $redis_field))
		{
			if ($this->predis->hget($redis_key, $redis_field) === $user)
			{
				return;
			}
		}

		$this->predis->hset($redis_key, $redis_field, $user);
		$this->predis->expire($redis_key, self::TTL);
		unset($this->local[$schema][$id]);
	}
}
