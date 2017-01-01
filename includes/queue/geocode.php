<?php

namespace eland\task;

use Predis\Client as Redis;
use Doctrine\DBAL\Connection as db;
use eland\cache;
use eland\queue;
use Monolog\Logger;

class geocode
{
	protected $queue;
	protected $monolog;
	protected $cache;
	protected $db;
	protected $redis;

	protected $curl;
	protected $geocoder;

	public function __construct(Redis $redis, db $db, cache $cache, queue $queue, Logger $monolog)
	{
		$this->redis = $redis;
		$this->queue = $queue;
		$this->monolog = $monolog;
		$this->cache = $cache;
		$this->db = $db;

		$this->curl = new \Ivory\HttpAdapter\CurlHttpAdapter();
		$this->geocoder = new \Geocoder\ProviderAggregator();

		$this->geocoder->registerProviders([
			new \Geocoder\Provider\GoogleMaps(
				$this->curl, 'nl', 'be', true
			),
		]);

		$this->geocoder->using('google_maps')->limit(1);
	}

	public function process(array $data)
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

		$user = readuser($uid, false, $sch);

		$log_user = 'user: ' . $sch . '.' . $user['letscode'] . ' ' . $user['name'] . ' (' . $uid . ')';

		$geo_status_key = 'geo_status_' . $adr;

		$key = 'geo_' . $adr;

		if (!$this->cache->exists($geo_status_key))
		{
			return;
		}

		$this->cache->set($geo_status_key, ['value' => 'error'], 31536000); // 1 year

		try
		{
			$address_collection = $this->geocoder->geocode($adr);

			if (is_object($address_collection))
			{
				$address = $address_collection->first();

				$ary = [
					'lat'	=> $address->getLatitude(),
					'lng'	=> $address->getLongitude(),
				];

				$this->cache->set($key, $ary);
				$this->cache->del($geo_status_key);
				$this->cache->del('geo_sleep');

				$log = 'Geocoded: ' . $adr . ' : ' . implode('|', $ary);

				$this->monolog->info('(cron) ' . $log . ' ' . $log_user, ['schema' => $sch]);

				return;
			}

			$log_1 = 'Geocode return NULL for: ' . $adr;

		}

		catch (Exception $e)
		{
			$log = 'Geocode adr: ' . $adr . ' exception: ' . $e->getMessage();

			return;
		}

		$this->monolog->info('cron geocode: ' . $log . ' ' . $log_user, ['schema' => $sch]);

		return;
	}

	public function queue(array $data)
	{
		if (!isset($data['schema']))
		{
			$this->monolog->debug('no schema set for geocode task');
			return;
		}

		if (!isset($data['uid']))
		{
			$this->monolog->debug('no uid set for geocode task');
			return;
		}

		if (!isset($data['adr']))
		{
			$this->monolog->debug('no adr set for geocode task');
			return;
		}

		$data['adr'] = trim($data['adr']);

		$key = 'geo_' . $data['adr'];
		$status_key = 'geo_status_' . $data['adr'];

// delete hashed status

		$this->cache->del(sha1('geo_status_' . $data['adr']));

//

		if ($this->cache->exists($key))
		{
			return false;
		}

		if ($this->cache->get($status_key) == ['value' => 'error'])
		{
			return false;
		}

// move to unhashed keys

		$sha = sha1($key);

		if ($this->cache->exists($sha))
		{
			$prdata = $this->cache->get($sha);
			$this->cache->set($key, $prdata);
			$this->cache->del($sha);

			return false;
		}

//

		$this->cache->set($status_key, ['value' => 'queue'], 2592000);  // 30 days

		$this->queue->set('geocode', $data);
	}

	public function run($schema)
	{
		$log_ary = [];

		$st = $this->db->prepare('select c.value, c.id_user
			from ' . $schema . '.contact c, ' . $schema . '.type_contact tc, ' . $schema . '.users u
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
				'schema'	=> $schema,
			];

			if ($this->queue($data) !== false)
			{
				$log_ary[] = link_user($row['id_user'], $schema, false, true) . ': ' . $data['adr'];
			}
		}

		if (count($log_ary))
		{
			$this->monolog->info('Addresses queued for geocoding: ' . implode(', ', $log_ary), ['schema' => $schema]);
		}
	}
}
