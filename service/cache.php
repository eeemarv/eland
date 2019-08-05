<?php declare(strict_types=1);

namespace service;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;
use Monolog\Logger;

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

class cache
{
	protected $db;
	protected $redis;
	protected $monolog;

	public function __construct(db $db, Redis $redis, Logger $monolog)
	{
		$this->db = $db;
		$this->redis = $redis;
		$this->monolog = $monolog;
	}

	/*
	 *
	 */

	public function set(string $id, array $data = [], int $expires = 0)
	{
		$id = trim($id);
		$data = json_encode($data);

		if (!strlen($id))
		{
			$error = 'Cache: no id set for data ' . $data;
			$this->monolog->error($error);
			return $error;
		}

		if (!ctype_digit((string) $expires))
		{
			$error = 'Cache: ' . $id . ' -> error Expires is no number: ' . $expires . ', data: ' . $data;
			$this->monolog->error($error);
			return $error;
		}

		$this->redis->set('cache_' . $id, $data);

		if ($expires)
		{
			$this->redis->expire('cache_' . $id, $expires);
		}

		$insert = [
			'id'			=> $id,
			'data'			=> $data,
		];

		if ($expires && $expires !== 0)
		{
			$insert['expires'] = gmdate('Y-m-d H:i:s', time() + $expires);
		}

		try
		{
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
		catch(Exception $e)
		{
			$this->db->rollback();
			$this->redis->del($id);
			echo 'Database transactie niet gelukt (cache).';
			$this->monolog->debug('Database transactie niet gelukt (queue). ' . $data . ' -- ' . $e->getMessage());
			throw $e;
			exit;
		}
	}

	/*
	 *
	 */

	public function get(string $id, bool $decoded = true)
	{
		if (!$id)
		{
			return $decoded ? [] : '';
		}

		$id = trim($id);

		$data = $this->redis->get('cache_' . $id);

		if ($data)
		{
			if (!$decoded)
			{
				return $data;
			}

			return json_decode($data, true);
		}

		$row = $this->db->fetchAssoc('select data, expires
			from xdb.cache
			where id = ?
				and (expires < timezone(\'utc\'::text, now())
					or expires is null)', [$id]);

		if ($row)
		{
			$this->redis->set('cache_' . $id, $row['data']);

			if (isset($data['expires']))
			{
				$this->redis->expireat('cache_' . $id, $data['expires']);
			}

			if (!$decoded)
			{
				return $row['data'];
			}

			return json_decode($row['data'], true);
		}

		return $decoded ? [] : '';
	}

	/**
	 *
	 */

	public function exists(string $id)
	{
		$id = trim($id);

		if (!$id)
		{
			return false;
		}

		if ($this->redis->exists('cache_' . $id))
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

	public function expire(string $id, int $time)
	{
		$id = trim($id);

		if (!$id)
		{
			return;
		}

		$this->redis->expire('cache_' . $id, $time);

		$time = gmdate('Y-m-d H:i:s', $time);

		$this->db->update('xdb.cache', ['expires' => $time], ['id' => $id]);

		return;
	}

	/**
	 *
	 */

	public function del(string $id)
	{
		$id = trim($id);

		if (!$id)
		{
			return;
		}

		$this->redis->del('cache_' . $id);

		$this->db->delete('xdb.cache', ['id' => $id]);

		return;
	}

	/**
	 *
	 */

	public function cleanup()
	{
		$this->db->executeQuery('delete from xdb.cache
			where expires < timezone(\'utc\'::text, now())
				and expires is not null');

		return;
	}
}
