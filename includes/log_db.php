<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;

class log_db
{
	protected $db;
	protected $redis;

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

			$insert = [
				'schema'		=> $log['context']['schema'] ?? $log['extra']['schema'],
				'user_id'		=> $log['extra']['user_id'] ?? 0,
				'user_schema'	=> $log['extra']['user_schema'],
				'letscode'		=> $log['extra']['letscode'],
				'username'		=> $log['extra']['username'] ,
				'ip'			=> $log['extra']['ip'],
				'ts'			=> $log['datetime']['date'],
				'type'			=> $log['level_name'],
				'event'			=> $log['message'],
				'letscode'		=> $log['extra'],
				'data'			=> $log_json,
			];

			$this->db->insert('eland_extra.logs', $insert);
		}
	}
}
