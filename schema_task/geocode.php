<?php

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use service\cache;
use Monolog\Logger;
use queue\geocode as geocode_queue;

use service\schedule;
use service\systems;

class geocode extends schema_task
{
	protected $queue;
	protected $monolog;
	protected $cache;
	protected $db;
	protected $curl;
	protected $geocoder;
	protected $geocode_queue;

	public function __construct(
		db $db,
		cache $cache,
		Logger $monolog,
		geocode_queue $geocode_queue,
		schedule $schedule,
		systems $systems
	)
	{
		parent::__construct($schedule, $systems);
		$this->monolog = $monolog;
		$this->cache = $cache;
		$this->db = $db;
		$this->geocode_queue = $geocode_queue;
	}

	public function process():void
	{
		if (getenv('GEO_BLOCK') === '1')
		{
			error_log('geo coding is blocked.');
			return;
		}

		$log_ary = [];

		$st = $this->db->prepare('select c.value, c.id_user
			from ' . $this->schema . '.contact c, ' .
				$this->schema . '.type_contact tc, ' .
				$this->schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'adr\'
				and c.id_user = u.id
				and u.status in (1, 2)');

		$st->execute();

		while ($row = $st->fetch())
		{
			$data = [
				'adr'		=> trim($row['value']),
				'uid'		=> $row['id_user'],
				'schema'	=> $this->schema,
			];

			$key = 'geo_' . $data['adr'];
			$status_key = 'geo_status_' . $data['adr'];

			if ($this->cache->exists($key))
			{
				continue;
			}

			if ($this->cache->get($status_key) == ['value' => 'error'])
			{
				continue;
			}

			$this->geocode_queue->queue($data);

			$log = link_user($row['id_user'], $this->schema, false, true);
			$log .= ': ';
			$log .= $data['adr'];

			$log_ary[] = $log;

			$this->cache->set($status_key,
				['value' => 'queue'],
				2592000);  // 30 days
		}

		if (count($log_ary))
		{
			$this->monolog->info('Addresses queued for geocoding: ' .
				implode(', ', $log_ary),
				['schema' => $this->schema]);
		}
	}

	public function is_enabled():bool
	{
		return true;
	}

	public function get_interval():int
	{
		return 86400;
	}
}
