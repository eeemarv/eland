<?php

namespace service;

use Doctrine\DBAL\Connection as db;

class groups
{
	private $db;
	private $schemas = [];
	private $hosts = [];
	private $overall_domain;

	public function __construct(db $db)
	{
		$this->db = $db;

		$this->overall_domain = getenv('OVERALL_DOMAIN');

		$schemas_db = $this->db->fetchAll('select schema_name from information_schema.schemata') ?: [];
		$schemas_db = array_map(function($row){ return $row['schema_name']; }, $schemas_db);

		foreach ($schemas_db as $s)
		{
			$up_s = strtoupper($s);
			$env = getenv('SCHEMA_' . $up_s);
			$h = $s;
			if (!$env && strpos($s, 'lets') === 0) 
			{
				$h = substr($s, 4);
				$env = getenv('SCHEMA_' . strtoupper($h));
			}
			if (!$env)
			{
				continue;
			}

			$h .= '.' . $this->overall_domain;

			$this->schemas[$h] = $s;
			$this->hosts[$s] = $h;
		}
	}

	public function get_schemas()
	{
		return $this->schemas;
	}

	public function get_hosts()
	{
		return $this->hosts;
	}

	public function get_schema($host)
	{
		return $this->schemas[$host] ?? false;
	}

	public function get_host($schema)
	{
		return $this->hosts[$schema] ?? false;
	}

	public function count()
	{
		return count($this->schemas);
	}
}
