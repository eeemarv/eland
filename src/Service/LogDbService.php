<?php declare(strict_types=1);

namespace App\Service;

use App\Monolog\RedisHandler;
use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;

class LogDbService
{
	const MAX_POP = 500;

	protected $db;
	protected $predis;

	public function __construct(Db $db, Predis $predis)
	{
		$this->db = $db;
		$this->predis = $predis;
	}

	public function update():void
	{
		for ($i = 0; $i < self::MAX_POP; $i++)
		{
			$log_json = $this->predis->lpop(RedisHandler::KEY);

			if (!isset($log_json))
			{
				break;
			}

			error_log('LPOP LOG:: ' . $log_json);

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
