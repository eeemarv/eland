<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;

class LogDbService
{
	protected $db;
	protected $predis;

	public function __construct(Db $db, Predis $predis)
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

			$schema = $log['extra']['schema'] ?? '';

			if (!$schema)
			{
				continue;
			}

			if ($log['message'] === '< 200')
			{
				continue;
			}

			if ($log['message'] === 'Notified event "{event}" to listener "{listener}".')
			{
				continue;
			}

			$user_id = $log['context']['user_id'] ?? $log['extra']['user_id'] ?? 0;
			$user_id = ctype_digit((string) $user_id) ? $user_id : 0;

			$insert = [
				'schema'		=> $schema,
				'user_id'		=> $user_id,
				'user_schema'	=> $user_id ? ($log['extra']['user_schema'] ?? '') : '',
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