<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

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

class QueueService
{
	public function __construct(
		protected Db $db,
		protected LoggerInterface $logger
	)
	{
	}

	public function set(string $topic, array $data, int $priority):void
	{
		if (!strlen($topic))
		{
			$this->logger->error('queue: no topic. ' . json_encode($data));
			return;
		}

		$insert = [
			'topic'				=> $topic,
			'data'				=> json_encode($data),
			'priority'		=> $priority,
		];

		$this->db->insert('xdb.queue', $insert);
	}

	public function get(array $topics = []):array
	{
		$sql_where = $sql_params = $sql_types = [];

		if (count($topics))
		{
			$sql_where[] = 'topic in (?)';
			$sql_params[] = $topics;
			$sql_types[] = Db::PARAM_STR_ARRAY;
		}

		$sql_where = count($sql_where) ? ' where ' . implode(' and ', $sql_where) : '';

		$query = 'select topic, data, id, priority
				from xdb.queue
				' . $sql_where . '
				order by priority desc, id asc
				limit 1';

		$stmt = $this->db->executeQuery($query, $sql_params, $sql_types);

		if ($row = $stmt->fetch())
		{
			$return = [
				'data'		=> json_decode($row['data'], true),
				'id'		=> $row['id'],
				'topic'		=> $row['topic'],
				'priority'	=> $row['priority'],
			];

			$this->db->delete('xdb.queue', ['id' => $row['id']]);

			error_log('Queue delete: ' . $row['id']);

			return $return;
		}

		return [];
	}

	public function count(string $topic = ''):int
	{
		if ($topic)
		{
			return $this->db->fetchOne('select count(*)
				from xdb.queue
				where topic = ?', [$topic], [\PDO::PARAM_STR]);
		}

		return $this->db->fetchOne('select count(*) from xdb.queue');
	}
}
