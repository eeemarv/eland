<?php declare(strict_types=1);

namespace App\Service;

use Redis;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

/*
                          Table "xdb.cache"
 Column  |            Type             |              Modifiers
---------+-----------------------------+--------------------------------------
 id      | character varying(255)      | not null
 data    | jsonb                       |
 ts      | timestamp without time zone | default timezone('utc'::text, now())
 expires | timestamp without time zone |
Indexes:
    "cache_pkey" PRIMARY KEY, btree (id)
*/

class CacheService
{
	const PREFIX = 'cache.';

	public function __construct(
		protected Db $db,
		protected Redis $redis,
		protected LoggerInterface $logger
	)
	{
	}

	public function set(
		string $id,
		array $data = [],
		int $expires = 0
	):void
	{
		$data = json_encode($data);

		if (!strlen($id))
		{
			throw new \LogicException('Cache: no id set for data ' . $data);
		}

		$key = self::PREFIX . $id;

		$this->redis->set($key, $data);

		if ($expires > 0)
		{
			$this->redis->expire($key, $expires);
		}

		$insert = [
			'id'			=> $id,
			'data'			=> $data,
		];

		if ($expires > 0)
		{
			$insert['expires'] = gmdate('Y-m-d H:i:s', time() + $expires);
		}

		$this->db->beginTransaction();

		if ($this->db->fetchOne('select id
			from xdb.cache
			where id = ?', [$id], [\PDO::PARAM_STR]))
		{
			$this->db->update('xdb.cache',
				['data' => $data],
				['id' => $id]);
		}
		else
		{
			$this->db->insert('xdb.cache', $insert);
		}

		$this->db->commit();
	}

	public function get(string $id):array
	{
		return json_decode($this->get_raw($id), true);
	}

	public function get_raw(string $id):string
	{
		if (!strlen($id))
		{
			throw new \LogicException('No id for cache get()');
		}

		$data = $this->redis->get(self::PREFIX . $id);

		if (is_string($data))
		{
			return $data;
		}

		$row = $this->db->fetchAssociative('select data, expires
			from xdb.cache
			where id = ?
				and (expires < timezone(\'utc\', now())
					or expires is null)',
			[$id], [\PDO::PARAM_STR]);

		if ($row)
		{
			$data = $row['data'];

			$this->redis->set(self::PREFIX . $id, $data);

			if (isset($data['expires']))
			{
				$expire_at = strtotime($data['expires'] . ' UTC');
				$this->redis->expireAt(self::PREFIX . $id, $expire_at);
			}

			return $data;
		}

		return '{}';
	}

	public function exists(string $id):bool
	{
		if (!strlen($id))
		{
			throw new \LogicException('No id set for cache::exists()');
		}

		if ($this->redis->exists(self::PREFIX . $id))
		{
			return true;
		}

		$exists = $this->db->fetchOne('select id
			from xdb.cache
			where id = ?
				and (expires < timezone(\'utc\', now())
					or expires is null)',
			[$id], [\PDO::PARAM_STR]);

		if ($exists)
		{
			return true;
		}

		return false;
	}

	public function expire(string $id, int $time):void
	{
		if (!strlen($id))
		{
			throw new \LogicException('No id set for cache::expire()');
		}

		$this->redis->expire(self::PREFIX . $id, $time);

		$time = gmdate('Y-m-d H:i:s', $time);

		$this->db->update('xdb.cache', ['expires' => $time], ['id' => $id]);
	}

	public function del(string $id):void
	{
		if (!strlen($id))
		{
			throw new \LogicException('No id set for cache::del()');
		}

		$this->redis->del(self::PREFIX . $id);

		$this->db->delete('xdb.cache', ['id' => $id]);
	}

	public function cleanup():void
	{
		$this->db->executeStatement('delete from xdb.cache
			where expires < timezone(\'utc\', now())
				and expires is not null');
	}
}
