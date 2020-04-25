<?php declare(strict_types=1);

namespace App\Service;

use App\Monolog\RedisHandler;
use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;

class LogDbService
{
	const MAX_POP = 500;

	protected Db $db;
	protected Predis $predis;
	protected SystemsService $systems_service;

	public function __construct(
		Db $db,
		Predis $predis,
		SystemsService $systems_service
	)
	{
		$this->db = $db;
		$this->predis = $predis;
		$this->systems_service = $systems_service;
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

			unset($user_id);

			$log = json_decode($log_json, true);

			$context = $log['context'];
			$extra = $log['extra'];

			if (isset($context['schema']))
			{
				$schema = $context['schema'];

				if (!$schema)
				{
					continue;
				}
			}
			else
			{
				if (!isset($extra['system']))
				{
					return;
				}

				$system = $extra['system'];
				$schema = $this->systems_service->get_schema($system);
			}

			if (!$schema)
			{
				continue;
			}

			$user_schema = $schema;

			if (isset($extra['org_system'])
				&& $extra['org_system'])
			{
				$org_schema = $this->systems_service->get_schema($context['org_system']);

				if ($org_schema)
				{
					$user_schema = $org_schema;
				}
			}

			if (isset($extra['logins'])
				&& isset($extra['logins'][$user_schema]))
			{
				$user_id = $extra['logins'][$user_schema];
			}

			$insert = [
				'schema'		=> $schema,
				'ts'			=> $log['datetime']['date'],
				'type'			=> $log['level_name'],
				'event'			=> $log['message'],
				'data'			=> $log_json,
			];

			if (isset($user_id) && ctype_digit((string) $user_id))
			{
				$insert['user_id'] = $user_id;
				$insert['user_schema'] = $user_schema;
			}

			if (isset($user_id) && $user_id === 'master')
			{
				$insert['is_master'] = true;
			}

			if (isset($log['extra']['ip']))
			{
				$insert['ip'] = $log['extra']['ip'];
			}

			$this->db->insert('xdb.logs', $insert);
		}
	}
}
