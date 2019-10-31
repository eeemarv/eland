<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Model\SchemaTask;
use Doctrine\DBAL\Connection as Db;
use App\Service\UserCacheService;
use App\Service\Schedule;
use App\Service\SystemsService;

class SyncUserCacheTask extends SchemaTask
{
	protected $db;
	protected $user_cache_service;

	public function __construct(
		Db $db,
		UserCacheService $user_cache_service,
		Schedule $schedule,
		SystemsService $systems_service
	)
	{
		parent::__construct($schedule, $systems_service);
		$this->db = $db;
		$this->user_cache_service = $user_cache_service;
	}

	public function get_name():string
	{
		return 'sync_user_cache';
	}

	public function process():void
	{
		$user_ids = [];

		$rs = $this->db->prepare('select id
			from ' . $this->schema . '.users');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$user_ids[] = $row['id'];
		}

		foreach ($user_ids as $id)
		{
			$this->user_cache_service->sync($id, $this->schema);
		}
	}

	public function is_enabled():bool
	{
		return true;
	}

	public function get_interval():int
	{
		return 43200;
	}
}
