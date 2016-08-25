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

	function run()
	{
		$treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 30);

		$this->db->executeQuery('delete from eland_extra.logs
			where ts < ?', [$treshold]);
	}
}
