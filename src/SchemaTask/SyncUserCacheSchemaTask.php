<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use App\Service\UserCacheService;

class SyncUserCacheSchemaTask implements SchemaTaskInterface
{
	public function __construct(
		protected Db $db,
		protected UserCacheService $user_cache_service
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

		$rs = $this->db->prepare('select id
			from ' . $schema . '.users');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$user_ids[] = $row['id'];
		}

		foreach ($user_ids as $id)
		{
			$this->user_cache_service->sync($id, $schema);
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
