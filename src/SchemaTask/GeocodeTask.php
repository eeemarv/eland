<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Model\SchemaTask;
use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use Psr\Log\LoggerInterface;
use App\Queue\geocode as geocode_queue;
use App\Service\Schedule;
use App\Service\SystemsService;
use App\Render\account_str;

class GeocodeTask extends SchemaTask
{
	protected $queue;
	protected $logger;
	protected $cache_service;
	protected $db;
	protected $curl;
	protected $geocoder;
	protected $geocode_queue;
	protected $account_str;

	public function __construct(
		Db $db,
		CacheService $cache_service,
		LoggerInterface $logger,
		geocode_queue $geocode_queue,
		Schedule $schedule,
		SystemsService $systems_service,
		account_str $account_str
	)
	{
		parent::__construct($schedule, $systems);
		$this->logger = $logger;
		$this->cache_service = $cache_service;
		$this->db = $db;
		$this->geocode_queue = $geocode_queue;
		$this->account_str = $account_str;
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

			if ($this->cache_service->exists($key))
			{
				continue;
			}

			if ($this->cache_service->get($status_key) == ['value' => 'error'])
			{
				continue;
			}

			$this->geocode_queue->queue($data, 0);

			$log = $this->account_str->get_with_id($row['id_user'], $this->schema);
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
