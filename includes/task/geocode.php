<?php

namespace eland\task;

use eland\base_task;
use Doctrine\DBAL\Connection as db;
use eland\cache;
use Monolog\Logger;
use eland\queue\geocode as geocode_queue;

class geocode extends base_task
{
	protected $queue;
	protected $monolog;
	protected $cache;
	protected $db;

	protected $curl;
	protected $geocoder;

	protected $geocode_queue;

	public function __construct(db $db, cache $cache, Logger $monolog, geocode_queue $geocode_queue)
	{
		$this->monolog = $monolog;
		$this->cache = $cache;
		$this->db = $db;
		$this->geocode_queue = $geocode_queue;
	}

	public function run()
	{
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

			$this->queue_geocode->queue($data);

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
		return 1800;
	}
}
