<?php

namespace service;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;

class log_db
{
	private $db;
	private $redis;

	public function __construct(db $db, Redis $redis)
	{
		$this->db = $db;
		$this->redis = $redis;
	}

	/*
	*
	*/

	public function update($count = 500)
	{
		for ($i = 0; $i < $count; $i++)
		{
			$log_json = $this->redis->lpop('monolog_logs');

			if (!isset($log_json))
			{
				break;
			}

			$log = json_decode($log_json, true);

			$sch = $log['context']['schema'] ?? $log['extra']['schema'];

			if (!isset($sch))
			{
				continue;
			}

			$user_id = $log['context']['user_id'] ?? $log['extra']['user_id'] ?? 0;

			$user_id = ctype_digit((string) $user_id) ? $user_id : 0;

			$insert = [
				'schema'		=> $log['context']['schema'] ?? $log['extra']['schema'],
				'user_id'		=> $user_id,
				'user_schema'	=> $log['extra']['user_schema'] ?? '',
				'letscode'		=> $log['context']['letscode'] ?? $log['extra']['letscode'] ?? '',
				'username'		=> $log['context']['username'] ?? $log['extra']['username'] ?? '',
				'ip'			=> $log['extra']['ip'] ?? '',
				'ts'			=> $log['datetime']['date'],
				'type'			=> $log['level_name'],
				'event'			=> $log['message'],
				'data'			=> $log_json,
			];

			$this->db->insert('xdb.logs', $insert);
		}
	}
}
