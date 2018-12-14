<?php

namespace service;

use Doctrine\DBAL\Connection as db;

class groups
{
	protected $db;
	protected $schemas = [];
	protected $hosts = [];
	protected $systems_schemas = [];
	protected $schemas_systems = [];
	protected $overall_domain;

	const IGNORE = [
		'xdb'					=> true,
		'template'				=> true,
		'public'				=> true,
		'c'						=> true,
		'e'						=> true,
		'temp'					=> true,
		'information_schema'	=> true,
		'migration'				=> true,
		'pg_catalog'			=> true,
	];

	public function __construct(db $db)
	{
		$this->db = $db;

		$this->overall_domain = getenv('OVERALL_DOMAIN');

		$link_system_schema = getenv('APP_LINK_SYSTEM_SCHEMA');
		$link_system_schema = explode(',', $link_system_schema);
		$env_schemas_systems = [];

		foreach($link_system_schema as $entry)
		{
			[$system, $schema] = explode(':', $entry);
			$env_schemas_systems[$schema] = $system;
		}

		$rs = $this->db->prepare('select schema_name
			from information_schema.schemata');
		$rs->execute();

		while($row = $rs->fetch())
		{
			$schema = $row['schema_name'];

			if (isset(self::IGNORE[$schema]))
			{
				continue;
			}

			$system = $env_schemas_systems[$schema] ?? $schema;
			$host = $schema . '.' . $this->overall_domain;

			$this->schemas[$host] = $schema;
			$this->hosts[$schema] = $host;

			$this->systems_schemas[$system] = $schema;
			$this->schemas_systems[$schema] = $system;
		}

/*
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
*/
	}

	public function get_schemas():array
	{
		return $this->schemas;
	}

	public function get_hosts():array
	{
		return $this->hosts;
	}

	public function get_schema(string $host):?string
	{
		return $this->schemas[$host] ?? null;
	}

	public function get_host(string $schema):?string
	{
		return $this->hosts[$schema] ?? null;
	}

	public function count():int
	{
		return count($this->schemas);
	}
}
