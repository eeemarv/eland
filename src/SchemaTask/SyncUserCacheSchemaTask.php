<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Cache\UserCache;
use Doctrine\DBAL\Connection as Db;

class SyncUserCacheSchemaTask implements SchemaTaskInterface
{
	public function __construct(
		protected Db $db,
		protected UserCache $user_cache
	)
	{
	}

	public static function get_default_index_name():string
	{
		return 'sync_user_cache';
	}

	public function run(string $schema, bool $update):void
	{
		$user_ids = [];

		$stmt = $this->db->prepare('select id
			from ' . $schema . '.users');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$user_ids[] = $row['id'];
		}

		foreach ($user_ids as $id)
		{
			// TO DO tag aware cache
			// $this->user_cache->sync($id, $schema);
		}
	}

	public function is_enabled(string $schema):bool
	{
		return true;
	}

	public function get_interval(string $schema):int
	{
		return 43200;
	}
}
