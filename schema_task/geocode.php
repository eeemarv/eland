<?php

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use service\cache;
use Monolog\Logger;
use queue\geocode as geocode_queue;

use service\schedule;
use service\groups;
use service\this_group;

class geocode extends schema_task
{
	private $queue;
	private $monolog;
	private $cache;
	private $db;

	private $curl;
	private $geocoder;

	private $geocode_queue;

	public function __construct(db $db, cache $cache, Logger $monolog, geocode_queue $geocode_queue,
		schedule $schedule, groups $groups, this_group $this_group)
	{
		parent::__construct($schedule, $groups, $this_group);
		$this->monolog = $monolog;
		$this->cache = $cache;
		$this->db = $db;
		$this->geocode_queue = $geocode_queue;
	}

	public function process()
	{
		if (getenv('GEO_BLOCK') === '1')
		{
			error_log('geo coding is blocked.');
			return;
		}

		$log_ary = [];

		$st = $this->db->prepare('select c.value, c.id_user
			from ' . $this->schema . '.contact c, ' . $this->schema . '.type_contact tc, ' . $this->schema . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'adr\'
				and c.id_user = u.id
				and u.status in (1, 2)');

		$st->execute();

		while (($row = $st->fetch()) && count($log_ary) < 20)
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

			$log_ary[] = link_user($row['id_user'], $this->schema, false, true) . ': ' . $data['adr'];

			$this->cache->set($status_key, ['value' => 'queue'], 2592000);  // 30 days
		}

		if (count($log_ary))
		{
			$this->monolog->info('Addresses queued for geocoding: ' . implode(', ', $log_ary), ['schema' => $this->schema]);
		}
	}

	public function get_interval()
	{
		return 900;
	}
}
