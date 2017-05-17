<?php

namespace service;

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

	public function set(string $topic, array $data, int $priority = 0, int $interval = 0)
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

	public function get(array $omit_topics = [])
	{
		$sql_where = $sql_params = $sql_types = [];

		if (count($omit_topics))
		{
			$sql_where[] = 'topic not in (?)';
			$sql_params[] = $omit_topics;
			$sql_types[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
		}

		$sql_where = count($sql_where) ? ' where ' . implode(' and ', $sql_where) : '';

		$query = 'select topic, data, id, priority
				from xdb.queue
				' . $sql_where . '
				order by priority desc, id asc
				limit 1';

		try{

			$stmt = $this->db->executeQuery($query, $sql_params, $sql_types);

			if ($row = $stmt->fetch())
			{
				$return = [
					'data'		=> json_decode($row['data'], true),
					'id'		=> $row['id'],
					'topic'		=> $row['topic'],
					'priority'	=> $row['priority'],
				];

				error_log('delete: ' . $row['id'] . ' : ' . $this->db->delete('xdb.queue', ['id' => $row['id']]));

				return $return;
			}

		}
		catch (\Exception $e)
		{
			error_log('err queue: ' . $e->getMessage());

			throw $e;

			return [];
		}

		return [];
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

