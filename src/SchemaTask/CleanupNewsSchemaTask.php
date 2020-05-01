<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class CleanupNewsSchemaTask implements SchemaTaskInterface
{
	protected Db $db;
	protected LoggerInterface $logger;

	public function __construct(
		Db $db,
		LoggerInterface $logger
	)
	{
		$this->db = $db;
		$this->logger = $logger;
	}

	public static function get_default_index_name():string
	{
		return 'cleanup_news';
	}

	public function run(string $schema, bool $update):void
	{
		$now = gmdate('Y-m-d H:i:s');

		$news = $this->db->fetchAll('select id, subject
			from ' . $schema . '.news
			where event_at < ?
				and is_sticky = \'f\'', [$now]);

		foreach ($news as $n)
		{
			$this->db->delete($schema . '.news', ['id' => $n['id']]);
			$this->logger->info('removed news item ' . $n['subject'],
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
