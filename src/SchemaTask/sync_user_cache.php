<?php declare(strict_types=1);

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use service\user_cache;
use service\schedule;
use service\systems;

class sync_user_cache extends schema_task
{
	protected $db;
	protected $user_cache;

	public function __construct(
		db $db,
		user_cache $user_cache,
		schedule $schedule,
		systems $systems
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
