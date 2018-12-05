<?php

namespace queue;

use queue\queue_interface;
use Doctrine\DBAL\Connection as db;
use service\cache;
use service\queue;
use service\user_cache;
use Monolog\Logger;
use service\geocode as geocode_service;

class geocode implements queue_interface
{
	protected $queue;
	protected $monolog;
	protected $cache;
	protected $db;
	protected $user_cache;

	protected $geocode_service;

	public function __construct(
		db $db,
		cache $cache,
		queue $queue,
		Logger $monolog,
		user_cache $user_cache,
		geocode_service $geocode_service
	)
	{
		$this->queue = $queue;
		$this->monolog = $monolog;
		$this->cache = $cache;
		$this->db = $db;
		$this->user_cache = $user_cache;
		$this->geocode_service = $geocode_service;
	}

	public function process(array $data):void
	{
		$adr = trim($data['adr']);
		$uid = $data['uid'];
		$sch = $data['schema'];

		if (!$adr || !$uid || !$sch)
		{
			error_log('geocode 1');
			return;
		}

		if ($this->cache->exists('geo_sleep'))
		{
			$this->monolog->debug('geocoding task is at sleep.', ['schema' => $sch]);
			return;
		}

		$user = $this->user_cache->get($uid, $sch);

		$log_user = 'user: ' . $sch . '.' .
			$user['letscode'] . ' ' .
			$user['name'] . ' (' . $uid . ')';

		$geo_status_key = 'geo_status_' . $adr;

		$key = 'geo_' . $adr;

		if (!$this->cache->exists($geo_status_key))
		{
			return;
		}

		$this->cache->set($geo_status_key, ['value' => 'error'], 31536000); // 1 year

		if (getenv('GEO_BLOCK') === '1')
		{
			error_log('geo coding is blocked. not processing: ' .
				json_encode($data));
			return;
		}

		$coords = $this->geocode_service->getCoordinates($adr);

		if (count($coords))
		{
			$this->cache->set($key, $coords);
			$this->cache->del($geo_status_key);
			$this->cache->del('geo_sleep');

			$log = 'Geocoded: ' . $adr . ' : ' . implode('|', $coords);

			$this->monolog->info('(cron) ' .
				$log . ' ' . $log_user,
				['schema' => $sch]);

			return;
		}

		$log = 'Geocode return NULL for: ' . $adr;
		$this->monolog->info('cron geocode: ' . $log .
			' ' . $log_user, ['schema' => $sch]);

		return;
	}

	public function queue(array $data):void
	{
		if (!$this->check_data($data))
		{
			return;
		}

		$data['adr'] = trim($data['adr']);

		$this->queue->set('geocode', $data);
	}

	/**
	 * address edits/adds/inits
	 */
	public function cond_queue(array $data):void
	{
		if (!$this->check_data($data))
		{
			return;
		}

		$log_ary = [];

		$key = 'geo_' . $data['adr'];
		$status_key = 'geo_status_' . $data['adr'];

		$log = json_encode($data);

		if ($this->cache->exists($key))
		{
			$this->monolog->info('Geocoding: key already exists for ' .
				json_encode($data), ['schema' => $data['schema']]);
			return;
		}

		if ($this->cache->get($status_key) == ['value' => 'error'])
		{
			$this->monolog->info('Geocoding: Error status exists for ' .
				json_encode($data), ['schema' => $data['schema']]);
			return;
		}

		$this->cache->set($status_key,
			['value' => 'queue'],
			2592000);  // 30 days

		$this->queue($data);

		$log = 'Queued for Geocoding: ';
		$log .= link_user($data['uid'], $data['schema'], false, true);
		$log .= ', ';
		$log .= $data['adr'];

		$this->monolog->info($log, ['schema' => $data['schema']]);
	}

	public function check_data(array $data):bool
	{
		if (!isset($data['schema']))
		{
			$this->monolog->debug('no schema set for geocode task');
			return false;
		}

		if (!isset($data['uid']))
		{
			$this->monolog->debug('no uid set for geocode task',
				['schema' => $data['schema']]);
			return false;
		}

		if (!isset($data['adr']))
		{
			$this->monolog->debug('no adr set for geocode task',
				['schema' => $data['schema']]);
			return false;
		}

		return true;
	}

	public function get_interval():int
	{
		return 120;
	}
}
