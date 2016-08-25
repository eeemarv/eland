<?php

namespace eland\task;

use Predis\Client as Redis;
use Doctrine\DBAL\Connection as db;
use eland\xdb;
use eland\queue;
use Monolog\Logger;
use eland\this_group;

class geocode
{
	protected $queue;
	protected $monolog;
	protected $xdb;
	protected $db;
	protected $redis;

	protected $curl;
	protected $geocoder;

	public function __construct(Redis $redis, db $db, xdb $xdb, queue $queue, Logger $monolog, this_group $this_group)
	{
		$this->redis = $redis;
		$this->queue = $queue;
		$this->monolog = $monolog;
		$this->xdb = $xdb;
		$this->db = $db;
		$this->this_group = $this_group;

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

		if ($this->redis->exists('geo_sleep'))
		{
			$this->monolog->debug('geocoding task is at sleep.', ['schema' => $sch]);
			return;
		}

		$user = readuser($uid, false, $sch);

		$log_user = 'user: ' . $sch . '.' . $user['letscode'] . ' ' . $user['name'] . ' (' . $uid . ')';

		$key = 'geo_' . $adr;

		$status = $this->redis->get($key);

		if ($status != 'q' && $status != 'f')
		{
			return;
		}

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

				$this->redis->set($key, json_encode($ary));
				$this->redis->expire($key, 31536000); // 1 year

				$log = 'Geocoded: ' . $adr . ' : ' . implode('|', $ary);

				$this->monolog->info('(cron) ' . $log . ' ' . $log_user, ['schema' => $sch]);

				return;
			}

			$log_1 = 'Geocode return NULL for: ' . $adr;

		}

		catch (Exception $e)
		{
			$log = 'Geocode adr: ' . $adr . ' exception: ' . $e->getMessage();
		}

		$this->monolog->info('cron geocode: ' . $log . ' ' . $log_user, ['schema' => $sch]);

		$this->redis->set($key, 'f');

		$this->redis->expire($key, 31536000); // 1 year

		$this->redis->set('geo_sleep', '1');
		$this->redis->expire('geo_sleep', 3600);

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

		if ($this->redis->exists($key))
		{
			return false;
		}

		$this->redis->set($key, 'q');
		$this->redis->expire($key, 2592000);

		$this->queue->set('geocode', $data);
	}

	public function run()
	{
		$log_ary = [];

		$st = $this->db->prepare('select c.value, c.id_user
			from contact c, type_contact tc, users u
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
				'schema'	=> $this->this_group->get_schema(),
			];

			if ($this->queue($data) !== false)
			{
				$log_ary[] = link_user($row['id_user'], false, false, true) . ': ' . $data['adr'];
			}
		}

		if (count($log_ary))
		{
			$this->monolog->info('Adresses queued for geocoding: ' . implode(', ', $log_ary));
		}
	}
}
