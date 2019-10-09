<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;
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

class Cache
{
	protected $db;
	protected $predis;
	protected $logger;

	const PREFIX = 'cache_';

	public function __construct(
		Db $db,
		Predis $predis,
		LoggerInterface $logger
	)
	{
		$this->db = $db;
		$this->predis = $predis;
		$this->logger = $logger;
	}

	public function set(string $id, array $data = [], int $expires = 0):void
	{
		$data = json_encode($data);

		if (!strlen($id))
		{
			throw new \LogicException('Cache: no id set for data ' . $data);
		}

		$this->predis->set(self::PREFIX . $id, $data);

		if ($expires > 0)
		{
			$this->predis->expire(self::PREFIX . $id, $expires);
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

		if ($this->db->fetchColumn('select id
			from xdb.cache
			where id = ?', [$id]))
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

	/**
	 *
	 */
	public function get(string $id):array
	{
		return json_decode($this->get_raw($id), true);
	}

	/**
	 *
	 */
	public function get_raw(string $id):string
	{
		if (!strlen($id))
		{
			throw new \LogicException('No id for cache get()');
		}

		$data = $this->predis->get(self::PREFIX . $id);

		if (isset($data))
		{
			return $data;
		}

		$row = $this->db->fetchAssoc('select data, expires
			from xdb.cache
			where id = ?
				and (expires < timezone(\'utc\'::text, now())
					or expires is null)', [$id]);

		if ($row)
		{
			$data = $row['data'];

			$this->predis->set(self::PREFIX . $id, $data);

			if (isset($data['expires']))
			{
				$expireat = strtotime($data['expires'] . ' UTC');
				$this->predis->expireat(self::PREFIX . $id, $expireat);
			}

			return $data;
		}

		return '{}';
	}

	/**
	 *
	 */
	public function exists(string $id):bool
	{
		if (!strlen($id))
		{
			throw new \LogicException('No id set for cache::exists()');
		}

		if ($this->predis->exists(self::PREFIX . $id))
		{
			return true;
		}

		$exists = $this->db->fetchColumn('select id
			from xdb.cache
			where id = ?
				and (expires < timezone(\'utc\'::text, now())
					or expires is null)', [$id]);

		if ($exists)
		{
			return true;
		}

		return false;
	}

	/**
	 *
	 */
	public function expire(string $id, int $time):void
	{
		if (!strlen($id))
		{
			throw new \LogicException('No id set for cache::expire()');
		}

		$this->predis->expire(self::PREFIX . $id, $time);

		$time = gmdate('Y-m-d H:i:s', $time);

		$this->db->update('xdb.cache', ['expires' => $time], ['id' => $id]);
	}

	/**
	 *
	 */
	public function del(string $id):void
	{
		if (!strlen($id))
		{
			throw new \LogicException('No id set for cache::del()');
		}

		$this->predis->del(self::PREFIX . $id);

		$this->db->delete('xdb.cache', ['id' => $id]);
	}

	/**
	 *
	 */
	public function cleanup():void
	{
		$this->db->executeQuery('delete from xdb.cache
			where expires < timezone(\'utc\'::text, now())
				and expires is not null');
	}
}
