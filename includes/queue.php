<?php

namespace eland;

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
		global $app;

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

		if (!ctype_digit((string) $priority))
		{
			$error = 'Queue topic: ' . $topic . ' -> error Priority is no number: ' . $priority;
			log_event('queue', $error);
			return $error;
		}

		$insert = [
			'topic'			=> $topic,
			'data'			=> json_encode($data),
			'priority'		=> $priority,
		];

		try
		{
			$app['db']->insert('eland_extra.queue', $insert);
		}
		catch(Exception $e)
		{
			$app['db']->rollback();
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

	public function get($topic, $count = 1, $call_func = false)
	{
		global $app;

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
			$app['db']->beginTransaction();

			$del_ids = $data = [];

			$st = $app['db']->prepare('select data, id, priority
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

//				error_log('fetch queue id : ' . $row['id'] . ' priority: ' . $row['priority'] . ' data: ' . $row['data']);
			}

			$app['db']->executeQuery('delete from eland_extra.queue where id in (?)',
				[$del_ids], [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

			$app['db']->commit();
		}
		catch(Exception $e)
		{
			$app['db']->rollback();
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
		global $app;

		$topic = trim($topic);

		if ($topic)
		{
			return $app['db']->fetchColumn('select count(*)
				from eland_extra.queue
				where topic = ?', [$topic]);
		}

		return $app['db']->fetchColumn('select count(*) from eland_extra.queue');
	}
}

