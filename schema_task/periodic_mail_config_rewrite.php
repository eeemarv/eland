<?php

namespace schema_task;

use model\schema_task;
use service\config;

use service\schedule;
use service\groups;
use service\this_group;

class periodic_mail_config_rewrite extends schema_task
{
	private $config;

	public function __construct(config $config,
		schedule $schedule, groups $groups, this_group $this_group)
	{
		parent::__construct($schedule, $groups, $this_group);
		$this->config = $config;
	}

	function process()
	{

// messages, interlets, forum, news, docs, new_users, leaving_users, transactions
// news, messages, interlets, forum, docs, new_users, leaving_users, transactions



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
		return 1800;
	}
}
