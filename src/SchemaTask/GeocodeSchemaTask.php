<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use Psr\Log\LoggerInterface;
use App\Queue\GeocodeQueue;
use App\Render\AccountStrRender;

class GeocodeSchemaTask implements SchemaTaskInterface
{
	protected $queue;
	protected $logger;
	protected $cache_service;
	protected $db;
	protected $curl;
	protected $geocoder;
	protected $geocode_queue;
	protected $account_str_render;
	protected $env_geo_block;

	public function __construct(
		Db $db,
		CacheService $cache_service,
		LoggerInterface $logger,
		GeocodeQueue $geocode_queue,
		AccountStrRender $account_str_render,
		string $env_geo_block
	)
	{
		$this->logger = $logger;
		$this->cache_service = $cache_service;
		$this->db = $db;
		$this->geocode_queue = $geocode_queue;
		$this->account_str_render = $account_str_render;
		$this->env_geo_block = $env_geo_block;
	}

	public static function get_default_index_name():string
	{
		return 'geocode';
	}

	public function run(string $schema, bool $update):void
	{
		if ($this->env_geo_block === '1')
		{
			error_log('geo coding is blocked.');
			return;
		}

		$log_ary = [];

		$st = $this->db->prepare('select c.value, c.id_user
			from ' . $schema . '.contact c, ' .
				$schema . '.type_contact tc, ' .
				$schema . '.users u
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
				'schema'	=> $schema,
			];

			$key = 'geo_' . $data['adr'];
			$status_key = 'geo_status_' . $data['adr'];

			if ($this->cache_service->exists($key))
			{
				continue;
			}

			if ($this->cache_service->get($status_key) == ['value' => 'error'])
			{
				continue;
			}

			$this->geocode_queue->queue($data, 0);

			$log = $this->account_str_render->get_with_id($row['id_user'], $schema);
			$log .= ': ';
			$log .= $data['adr'];

			$log_ary[] = $log;

			$this->cache_service->set($status_key,
				['value' => 'queue'],
				2592000);  // 30 days
		}

		if (count($log_ary))
		{
			$this->logger->info('Addresses queued for geocoding: ' .
				implode(', ', $log_ary),
				['schema' => $schema]);
		}
	}

	public function is_enabled(string $schema):bool
	{
		return true;
	}

	public function get_interval(string $schema):int
	{
		return 86400;
	}
}
