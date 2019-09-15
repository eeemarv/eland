<?php declare(strict_types=1);

namespace service;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Predis;

class log_db
{
	protected $db;
	protected $predis;

	public function __construct(db $db, Predis $predis)
	{
		$this->db = $db;
		$this->predis = $predis;
	}

	/**
	 *
	 */
	public function update(int $count = 500):void
	{
		for ($i = 0; $i < $count; $i++)
		{
			$log_json = $this->predis->lpop('monolog_logs');

			if (!isset($log_json))
			{
				break;
			}

			$log = json_decode($log_json, true);

			if (!isset($log['context']['schema']))
			{
				continue;
			}

			$user_id = $log['context']['user_id'] ?? $log['extra']['user_id'] ?? 0;

			$user_id = ctype_digit((string) $user_id) ? $user_id : 0;

			$insert = [
				'schema'		=> $log['context']['schema'] ?? '',
				'user_id'		=> $user_id,
				'user_schema'	=> $log['extra']['user_schema'] ?? '',
				'letscode'		=> '',
				'username'		=> '',
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
