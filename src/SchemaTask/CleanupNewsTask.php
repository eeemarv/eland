<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Model\SchemaTask;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\XdbService;

use App\Service\Schedule;
use App\Service\SystemsService;

class CleanupNewsTask extends SchemaTask
{
	protected $db;
	protected $xdb_service;
	protected $logger;

	public function __construct(
		Db $db,
		XdbService $xdb_service,
		LoggerInterface $logger,
		Schedule $schedule,
		SystemsService $systems_service
	)
	{
		parent::__construct($schedule, $systems_service);
		$this->db = $db;
		$this->xdb_service = $xdb_service;
		$this->logger = $logger;
	}

	public function get_name():string
	{
		return 'cleanup_news';
	}

	public function process():void
	{
		$now = gmdate('Y-m-d H:i:s');

		$news = $this->db->fetchAll('select id, headline
			from ' . $this->schema . '.news
			where itemdate < ?
				and sticky = \'f\'', [$now]);

		foreach ($news as $n)
		{
			$this->xdb_service->del('news_access', (string) $n['id'], $this->schema);
			$this->db->delete($this->schema . '.news', ['id' => $n['id']]);
			$this->logger->info('removed news item ' . $n['headline'],
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
