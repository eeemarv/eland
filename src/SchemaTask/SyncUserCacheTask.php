<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Model\SchemaTask;
use Doctrine\DBAL\Connection as Db;
use App\Service\UserCache;
use App\Service\Schedule;
use App\Service\Systems;

class SyncUserCacheTask extends SchemaTask
{
	protected $db;
	protected $user_cache;

	public function __construct(
		Db $db,
		UserCache $user_cache,
		Schedule $schedule,
		Systems $systems
	)
	{
		parent::__construct($schedule, $systems);
		$this->db = $db;
		$this->user_cache = $user_cache;
	}

	function process():void
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
			$this->user_cache->sync($id, $this->schema);
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
