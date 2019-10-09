<?php declare(strict_types=1);

namespace App\Queue;

use App\Queue\QueueInterface;
use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use App\Service\Queue;
use Psr\Log\LoggerInterface;
use App\Service\Geocode;
use App\Render\account_str;

class GeocodeQueue implements QueueInterface
{
	protected $queue;
	protected $logger;
	protected $cache_service;
	protected $db;
	protected $geocode;
	protected $account_str;

	public function __construct(
		Db $db,
		CacheService $cache_service,
		Queue $queue,
		LoggerInterface $logger,
		Geocode $geocode,
		account_str $account_str
	)
	{
		$this->queue = $queue;
		$this->logger = $logger;
		$this->cache_service = $cache_service;
		$this->db = $db;
		$this->geocode = $geocode;
		$this->account_str = $account_str;
	}

	public function process(array $data):void
	{
		$adr = trim($data['adr']);
		$uid = $data['uid'];
		$sch = $data['schema'];

		if (!$adr || !$uid || !$sch)
		{
			$this->logger->debug('geocoding process data missing: ' .
				json_encode($data),
				['schema' => $sch]);
			return;
		}

		if ($this->cache_service->exists('geo_sleep'))
		{
			$this->logger->debug('geocoding task is at sleep.',
				['schema' => $sch]);
			return;
		}

		$log_user = 'user: ' . $sch . '.' .
			$this->account_str->get_with_id($uid, $sch);

		$geo_status_key = 'geo_status_' . $adr;
		$key = 'geo_' . $adr;

		if (!$this->cache_service->exists($geo_status_key))
		{
			$this->logger->debug('geocoding proces geo_status_key missing: ' .
				$geo_status_key . ' for data ' . json_encode($data),
				['schema' => $sch]);
			return;
		}

		$this->cache_service->set($geo_status_key, ['value' => 'error'], 31536000); // 1 year

		if (getenv('GEO_BLOCK') === '1')
		{
			error_log('geo coding is blocked. not processing: ' .
				json_encode($data));
			return;
		}

		// lat, lng
		$coords = $this->geocode->getCoordinates($adr);

		if (count($coords))
		{
			$this->cache_service->set($key, $coords);
			$this->cache_service->del($geo_status_key);
			$this->cache_service->del('geo_sleep');

			$log = 'Geocoded: ' . $adr . ' : ' . implode('|', $coords);

			$this->logger->info($log . ' ' . $log_user,
				['schema' => $sch]);

			return;
		}

		$log = 'Geocode return NULL for: ' . $adr;
		$this->logger->info('cron geocode: ' . $log .
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

		if ($this->cache_service->exists($key))
		{
			$this->logger->info('Geocoding: key already exists for ' .
				json_encode($data), ['schema' => $data['schema']]);
			return;
		}

		if ($this->cache_service->get($status_key) == ['value' => 'error'])
		{
			$this->logger->info('Geocoding: Error status exists for ' .
				json_encode($data), ['schema' => $data['schema']]);
			return;
		}

		$this->cache_service->set($status_key,
			['value' => 'queue'],
			2592000);  // 30 days

		$this->queue($data, $priority);

		$log = 'Queued for Geocoding: ';
		$log .= $this->account_str->get_with_id($data['uid'], $data['schema']);
		$log .= ', ';
		$log .= $data['adr'];

		$this->logger->info($log, ['schema' => $data['schema']]);
	}

	public function check_data(array $data):bool
	{
		if (!isset($data['schema']))
		{
			$this->logger->debug('no schema set for geocode task');
			return false;
		}

		if (!isset($data['uid']))
		{
			$this->logger->debug('no uid set for geocode task',
				['schema' => $data['schema']]);
			return false;
		}

		if (!isset($data['adr']))
		{
			$this->logger->debug('no adr set for geocode task',
				['schema' => $data['schema']]);
			return false;
		}

		return true;
	}
}
