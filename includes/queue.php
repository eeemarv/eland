<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;

/*
                                        Table "eland_extra.queue"
  Column  |            Type             |                           Modifiers                            
----------+-----------------------------+----------------------------------------------------------------
 ts       | timestamp without time zone | default timezone('utc'::text, now())
 data     | jsonb                       | 
 topic    | character varying(60)       | not null
 priority | integer                     | default 0
 id       | bigint                      | not null default nextval('eland_extra.queue_id_seq'::regclass)
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

	public function set($topic, $data, $priority = 0)
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
			$this->db->insert('eland_extra.queue', $insert);
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

	public function get($topic, $count = 1, $call_func = false)
	{
		if (!strlen($topic))
		{
			return [];
		}

		if (!$count)
		{
			return [];
		}

		try
		{
			$this->db->beginTransaction();

			$del_ids = $data = [];

			$st = $this->db->prepare('select data, id, priority
				from eland_extra.queue
				where topic = ?
				order by priority desc, id asc
				limit ' . $count);

			$st->bindValue(1, $topic);

			$st->execute();

			while ($row = $st->fetch())
			{
				$d = json_decode($row['data'], true);

				if ($call_func)
				{
					if (!call_user_func($call_func, $d))
					{
						$del_ids[] = $row['id'];
					}
				}
				else
				{
					$del_ids[] = $row['id'];
				}

				$data[] = $d;
			}

			$this->db->executeQuery('delete from eland_extra.queue where id in (?)',
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

		return $data;
	}

	/**
	 *
	 */

	public function count($topic = false)
	{
		$topic = trim($topic);

		if ($topic)
		{
			return $this->db->fetchColumn('select count(*)
				from eland_extra.queue
				where topic = ?', [$topic]);
		}

		return $this->db->fetchColumn('select count(*) from eland_extra.queue');
	}
}

