<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;
use eland\groups;

class this_group
{
	private $db;
	private $redis;
	private $groups;
	private $schema;
	private $host;

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

	public function force($schema)
	{
		$this->schema = $schema;
		$this->host = $this->groups->get_host($schema);
		$this->db->exec('SET search_path TO ' . $schema);
	}

	public function get_schema()
	{
		return $this->schema;
	}

	public function get_host()
	{
		return $this->host;
	}

	public function get_name()
	{
		return readconfigfromdb('systemname', $this->schema);
	}

	public function get_tag()
	{
		return readconfigfromdb('systemtag', $this->schema);
	}

	public function get_currency()
	{
		return readconfigfromdb('currency', $this->schema);
	}

	public function get_currencyratio()
	{
		return readconfigfromdb('currencyratio', $this->schema);
	}

	public function get_newusertreshold()
	{
		return time() - readconfigfromdb('newuserdays', $this->schema) * 86400;
	}

	public function get_newuserdays()
	{
		return readconfigfromdb('newuserdays', $this->schema);
	}
}
