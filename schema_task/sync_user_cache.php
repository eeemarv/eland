<?php

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use service\user_cache;

use service\schedule;
use service\groups;
use service\this_group;

class sync_user_cache extends schema_task
{
	private $db;
	private $user_cache;

	public function __construct(db $db, user_cache $user_cache,
		schedule $schedule, groups $groups, this_group $this_group)
	{
		parent::__construct($schedule, $groups, $this_group);
		$this->db = $db;
		$this->user_cache = $user_cache;
	}

	function process()
	{
		$user_ids = [];

		$rs = $this->db->prepare('select id from ' . $this->schema . '.users');

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

	public function get_interval()
	{
		return 43200;
	}
}
