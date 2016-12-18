<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;

/*
                                        Table "xdb.queue"
  Column  |            Type             |                           Modifiers                            
----------+-----------------------------+----------------------------------------------------------------
 ts       | timestamp without time zone | default timezone('utc'::text, now())
 data     | jsonb                       | 
 topic    | character varying(60)       | not null
 priority | integer                     | default 0
 id       | bigint                      | not null default nextval('xdb.queue_id_seq'::regclass)
Indexes:
    "queue_pkey" PRIMARY KEY, btree (id)
    "queue_id_priority_idx" btree (id, priority)

*/

class queue
{
	private $db;
	private $monolog;

	public function __construct(db $db, Logger $monolog)
	{
		$this->db = $db;
		$this->monolog = $monolog;
	}

	/*
	 *
	 */

	public function set(string $topic, array $data, int $priority = 0)
	{
		if (!strlen($topic))
		{
			$error = 'No queue topic set for data ' . json_encode($data);
			$this->monolog->error('queue: ' . $error);
			return $error;
		}

		if (!$data)
		{
			$error = 'Queue topic: ' . $topic . ' -> No data set';
			$this->monolog->error('queue: ', $error);
			return $error;
		}

		if (!ctype_digit((string) $priority))
		{
			$error = 'Queue topic: ' . $topic . ' -> error Priority is no number: ' . $priority;
			$this->monolog->error('queue error: ', $error);
			return $error;
		}

		$insert = [
			'topic'			=> $topic,
			'data'			=> json_encode($data),
			'priority'		=> $priority,
		];

		try
		{
			$this->db->insert('xdb.queue', $insert);
		}
		catch(Exception $e)
		{
			$this->db->rollback();
			echo 'Database transactie niet gelukt (queue).';
			$this->monolog->debug('Database transactie niet gelukt (queue). ' . $e->getMessage());
			throw $e;
			exit;
		}
	}

	/*
	 *
	 */

	public function get(string $topic = '', $count = 1)
	{
		if (!$count)
		{
			return [];
		}

		$sql_where = $sql_params = $del_ids = $ret = [];

		if ($topic)
		{
			$sql_where[] = 'topic = ?';
			$sql_params[] = trim($topic);
		}
	
		$sql_where = count($sql_where) ? ' where ' . implode(' and ', $sql_where) : '';

		try
		{
			$this->db->beginTransaction();

			$st = $this->db->prepare('select topic, data, id, priority
				from xdb.queue
				' . $sql_where . '
				order by priority desc, id asc
				limit ' . $count);

			foreach ($sql_params as $k => $p)
			{
				$st->bindValue($k + 1, $p);
			}

			$st->execute();

			while ($row = $st->fetch())
			{
				$ret[] = [
					'data'	=> json_decode($row['data'], true),
					'id'	=> $row['id'],
					'topic'	=> $row['topic'],
					'prioritry'	=> $row['priority'],
				];

				$del_ids[] = $row['id'];
			}

			$this->db->executeQuery('delete from xdb.queue where id in (?)',
				[$del_ids], [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

			$this->db->commit();
		}
		catch(Exception $e)
		{
			$this->db->rollback();
			echo 'Database transactie niet gelukt (queue).';
			$this->monolog->debug('Database transactie niet gelukt (queue). ' . $e->getMessage());
			throw $e;
			exit;
		}

		return $ret;
	}

	/**
	 *
	 */

	public function count(string $topic = '')
	{
		$topic = trim($topic);

		if ($topic)
		{
			return $this->db->fetchColumn('select count(*)
				from xdb.queue
				where topic = ?', [$topic]);
		}

		return $this->db->fetchColumn('select count(*) from xdb.queue');
	}
}

