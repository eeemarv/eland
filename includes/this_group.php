<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
// use Predis\Client as redis;

class this_group
{
	private $schema = [];
	private $host = [];

	public function __construct(db $db)
	{
		$this->db = $db;
	}

}
