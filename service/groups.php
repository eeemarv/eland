<?php

namespace service;

use Doctrine\DBAL\Connection as db;

class groups
{
	protected $db;
	protected $schemas = [];
	protected $hosts = [];
	protected $overall_domain;

	public function __construct(db $db)
	{
		$this->db = $db;

		$this->overall_domain = getenv('OVERALL_DOMAIN');

		$schemas_db = $this->db->fetchAll('select schema_name
			from information_schema.schemata') ?: [];

		$schemas_db = array_map(function($row){
			return $row['schema_name']; }, $schemas_db);

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

	public function get_schemas():array
	{
		return $this->schemas;
	}

	public function get_hosts():array
	{
		return $this->hosts;
	}

	public function get_schema($host):?string
	{
		return $this->schemas[$host] ?? null;
	}

	public function get_host($schema):?string
	{
		return $this->hosts[$schema] ?? null;
	}

	public function count():int
	{
		return count($this->schemas);
	}
}
