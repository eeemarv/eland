<?php

namespace eland\task;

use eland\model\task;
use Doctrine\DBAL\Connection as db;

class cleanup_logs extends task
{
	protected $db;

	public function __construct(db $db)
	{
		$this->db = $db;
	}

	public function run()
	{
		// $schema is not used, logs from all schemas are cleaned up.

		$treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 30);

		$this->db->executeQuery('delete from xdb.logs
			where ts < ?', [$treshold]);
	}

	public function get_interval()
	{
		return 86400;
	}
}
