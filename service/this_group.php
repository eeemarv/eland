<?php

namespace service;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;
use service\groups;

class this_group
{
	protected $db;
	protected $redis;
	protected $groups;
	protected $schema;
	protected $host;

	public function __construct(groups $groups, db $db, Redis $redis)
	{
		$this->db = $db;
		$this->redis = $redis;
		$this->groups = $groups;

		$this->host = $_SERVER['SERVER_NAME'] ?? '';
		$this->schema = $this->host ? $this->groups->get_schema($this->host) : '';

		if ($this->schema)
		{
			$this->db->exec('set search_path to ' . $this->schema);
		}
	}

	public function force($schema):void
	{
		$this->schema = $schema;
		$this->host = $this->groups->get_host($schema);
		$this->db->exec('set search_path to ' . $schema);
	}

	public function get_schema():string
	{
		return $this->schema;
	}

	public function get_host():string
	{
		return $this->host;
	}
}
