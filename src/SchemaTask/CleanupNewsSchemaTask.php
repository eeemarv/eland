<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\XdbService;

class CleanupNewsSchemaTask implements SchemaTaskInterface
{
	protected $db;
	protected $xdb_service;
	protected $logger;

	public function __construct(
		Db $db,
		XdbService $xdb_service,
		LoggerInterface $logger
	)
	{
		$this->db = $db;
		$this->xdb_service = $xdb_service;
		$this->logger = $logger;
	}

	public static function get_default_index_name():string
	{
		return 'cleanup_news';
	}

	public function run(string $schema, bool $update):void
	{
		$now = gmdate('Y-m-d H:i:s');

		$news = $this->db->fetchAll('select id, headline
			from ' . $schema . '.news
			where itemdate < ?
				and sticky = \'f\'', [$now]);

		foreach ($news as $n)
		{
			$this->xdb_service->del('news_access', (string) $n['id'], $schema);
			$this->db->delete($schema . '.news', ['id' => $n['id']]);
			$this->logger->info('removed news item ' . $n['headline'],
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
