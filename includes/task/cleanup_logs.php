<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\xdb;

class cleanup_logs
{
	protected $db;
	protected $xdb;

	public function __construct(db $db, xdb $xdb)
	{
		$this->db = $db;
		$this->xdb = $xdb;
	}

	function run($schema)
	{
		// $schema is not used, logs from all schemas are cleaned up.

		$treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 30);

		$this->db->executeQuery('delete from xdb.logs
			where ts < ?', [$treshold]);
	}
}
