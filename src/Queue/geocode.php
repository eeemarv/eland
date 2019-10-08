<?php declare(strict_types=1);

namespace App\Queue;

use queue\queue_interface;
use Doctrine\DBAL\Connection as db;
use service\cache;
use service\queue;
use Monolog\Logger;
use service\geocode as geocode_service;
use render\account_str;

class geocode implements queue_interface
{
	protected $queue;
	protected $monolog;
	protected $cache;
	protected $db;
	protected $geocode_service;
	protected $account;

	public function __construct(
		db $db,
		cache $cache,
		queue $queue,
		Logger $monolog,
		geocode_service $geocode_service,
		account_str $account_str
	)
	{
		$this->queue = $queue;
		$this->monolog = $monolog;
		$this->cache = $cache;
		$this->db = $db;
		$this->geocode_service = $geocode_service;
		$this->account_str = $account_str;
	}

	public function process(array $data):void
	{
		$adr = trim($data['adr']);
		$uid = $data['uid'];
		$sch = $data['schema'];

		if (!$adr || !$uid || !$sch)
		{
			$this->monolog->debug('geocoding process data missing: ' .
				json_encode($data),
				['schema' => $sch]);
			return;
		}

		if ($this->cache->exists('geo_sleep'))
		{
			$this->monolog->debug('geocoding task is at sleep.',
				['schema' => $sch]);
			return;
		}

		$log_user = 'user: ' . $sch . '.' .
			$this->account_str->get_with_id($uid, $sch);

		$geo_status_key = 'geo_status_' . $adr;
		$key = 'geo_' . $adr;

		if (!$this->cache->exists($geo_status_key))
		{
			$this->monolog->debug('geocoding proces geo_status_key missing: ' .
				$geo_status_key . ' for data ' . json_encode($data),
				['schema' => $sch]);
			return;
		}

		$this->cache->set($geo_status_key, ['value' => 'error'], 31536000); // 1 year

		if (getenv('GEO_BLOCK') === '1')
		{
			error_log('geo coding is blocked. not processing: ' .
				json_encode($data));
			return;
		}

		// lat, lng
		$coords = $this->geocode_service->getCoordinates($adr);

		if (count($coords))
		{
			$this->cache->set($key, $coords);
			$this->cache->del($geo_status_key);
			$this->cache->del('geo_sleep');

			$log = 'Geocoded: ' . $adr . ' : ' . implode('|', $coords);

			$this->monolog->info($log . ' ' . $log_user,
				['schema' => $sch]);

			return;
		}

		$log = 'Geocode return NULL for: ' . $adr;
		$this->monolog->info('cron geocode: ' . $log .
			' ' . $log_user, ['schema' => $sch]);

		return;
	}

	public function queue(array $data, int $priority):void
	{
		if (!$this->check_data($data))
		{
			return;
		}

		$data['adr'] = trim($data['adr']);

		$this->queue->set('geocode', $data, $priority);
	}

	/**
	 * address edits/adds/inits
	 */
	public function cond_queue(array $data, int $priority):void
	{
		if (!$this->check_data($data))
		{
			return;
		}

		$data['adr'] = trim($data['adr']);

		$key = 'geo_' . $data['adr'];
		$status_key = 'geo_status_' . $data['adr'];

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

		$this->queue($data, $priority);

		$log = 'Queued for Geocoding: ';
		$log .= $this->account_str->get_with_id($data['uid'], $data['schema']);
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
}
