<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Model\SchemaTask;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\Xdb;

use App\Service\Schedule;
use App\Service\Systems;

class CleanupNewsTask extends SchemaTask
{
	protected $db;
	protected $xdb;
	protected $logger;

	public function __construct(
		Db $db,
		Xdb $xdb,
		LoggerInterface $logger,
		Schedule $schedule,
		Systems $systems
	)
	{
		parent::__construct($schedule, $systems);
		$this->db = $db;
		$this->xdb = $xdb;
		$this->logger = $logger;
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
			$this->xdb->del('news_access', (string) $n['id'], $this->schema);
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
