<?php

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
	public function __construct()
	{
	}

	/*
	 *
	 */

	public function set($topic, $data, $priority = 0)
	{
		global $db;

		if (!strlen($topic))
		{
			$error = 'No queue topic set for data ' . json_encode($data);
			log_event('queue', $error);
			return $error;
		}

		if (!$data)
		{
			$error = 'Queue topic: ' . $topic . ' -> No data set';
			log_event('queue', $error);
			return $error;
		}

		$insert = [
			'topic'			=> $topic,
			'data'			=> json_encode($data),
		];

		try
		{
			$db->insert('eland_extra.queue', $insert);
		}
		catch(Exception $e)
		{
			$db->rollback();
			error_log('error transaction eland extra.queue db: ' . $e->getMessage());
			echo 'Database transactie niet gelukt (queue).';
			log_event('debug', 'Database transactie niet gelukt (queue). ' . $e->getMessage());
			throw $e;
			exit;
		}
	}

	/*
	 *
	 */

	public function get($topic, $count = 1)
	{
		global $db;

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
			$ids = $data = [];

			$st = $db->prepare('select data, id, priority
				from eland_extra.queue
				where topic = ?
				order by priority desc, id asc
				limit ' . $count);

			$st->bindValue(1, $topic);

			$st->execute();

			while ($row = $st->fetch())
			{
				$ids[] = $row['id'];
				$data[] = json_decode($row['data'], true);

				error_log('fetch queue id : ' . $row['id'] . ' priority: ' . $row['priority'] . ' data: ' . $row['data']);
			}

			$db->executeQuery('delete from eland_extra.queue where id in (?)',
				[$ids], [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);
		}
		catch(Exception $e)
		{
			$db->rollback();
			error_log('error eland extra.queue db: ' . $e->getMessage());
			echo 'Database transactie niet gelukt (queue).';
			log_event('debug', 'Database transactie niet gelukt (queue). ' . $e->getMessage());
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
		global $db;

		$topic = trim($topic);

		if ($topic)
		{
			return $db->fetchColumn('select count(*)
				from eland_extra.queue
				where topic = ?', [$topic]);
		}

		return $db->fetchColumn('select count(*) from eland_extra.queue');
	}
}

