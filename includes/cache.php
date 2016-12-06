<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;
use Monolog\Logger;

/*
                          Table "eland_extra.cache"
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
	private $db;
	private $redis;
	private $monolog;

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

		$id = sha1($id);

		$this->redis->set('cache_' . $id, $data);

		if ($expires)
		{
			$this->redis->expire('cache_' . $id, $expires);
		}

		$insert = [
			'id'			=> $id,
			'data'			=> $data,
		];

		if ($expires)
		{
			$insert['expires'] = gmdate('Y-m-d H:i:s', time() + $expires);
		}

		try
		{
			$this->db->insert('eland_extra.cache', $insert);
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

	public function get(string $id)
	{
		$id = sha1(trim($id));

		if (!$id)
		{
			return [];
		}

		$data = $this->redis->get('cache_' . $id);

		if ($data)
		{
			return json_decode($data, true);
		}

		$row = $this->db->fetchAssoc('select data, expires
			from eland_extra.cache
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
		}

		return json_decode($row['data'], true);
	}

	/**
	 *
	 */

	public function expire(string $id, int $time)
	{
		$id = sha1(trim($id));

		$this->redis->expire('cache_' . $id, $time);

		$time = gmdate('Y-m-d H:i:s', $time);

		$this->db->update('eland_extra.cache', ['expires' => $time], ['id' => $id]);

		return;
	}

	/**
	 *
	 */

	public function cleanup()
	{
		$this->db->executeQuery('delete from eland_extra.cache
			where expires < timezone(\'utc\'::text, now()) and expires is not null');
		return; 
	}
}

