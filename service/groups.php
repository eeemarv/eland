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

	const TEMP_ALT = [
		'letsdurme'			=> 'durme',
		'letsdendermonde'	=> 'dendermonde',
	];

	public function __construct(db $db)
	{
		$this->db = $db;

		$this->overall_domain = getenv('OVERALL_DOMAIN');

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

			if (strpos($schema, 'pg_') === 0)
			{
				continue;
			}

			$system = self::TEMP_ALT[$schema] ?? $schema;
			$host = $system . '.' . $this->overall_domain;

			$this->schemas[$host] = $schema;
			$this->hosts[$schema] = $host;

			$this->systems_schemas[$system] = $schema;
			$this->schemas_systems[$schema] = $system;
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
