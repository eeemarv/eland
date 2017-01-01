<?php

namespace eland\task;

use eland\base_task;
use Doctrine\DBAL\Connection as db;
use eland\xdb;

class cleanup_logs extends base_task
{
	protected $db;
	protected $xdb;

	public function __construct(db $db, xdb $xdb)
	{
		$this->db = $db;
		$this->xdb = $xdb;
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
