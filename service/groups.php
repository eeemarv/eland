<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
// use Predis\Client as redis;

class groups
{
	private $schemas = [];
	private $hosts = [];
	private $overall_domain;

	public function __construct(db $db)
	{
		$this->db = $db;

		$this->overall_domain = getenv('OVERALL_DOMAIN');

		$schemas_db = $this->db->fetchAll('select schema_name from information_schema.schemata') ?: [];
		$schemas_db = array_map(function($row){ return $row['schema_name']; }, $schemas_db);
		$schemas_db = array_fill_keys($schemas_db, true);

		foreach ($_ENV as $key => $s)
		{
			if (strpos($key, 'SCHEMA_') !== 0 || (!isset($schemas_db[$s])))
			{
				continue;
			}

			$h = str_replace(['SCHEMA_', '___', '__'], ['', '-', '.'], $key);
			$h = strtolower($h);

			if (!strpos($h, '.' . $this->overall_domain))
			{
				$h .= '.' . $this->overall_domain;
			}

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

	public function get_template_vars($schema)
	{
		$return = [
			'tag'				=> readconfigfromdb('systemtag', $schema),
			'name'				=> readconfigfromdb('systemname', $schema),
			'currency'			=> readconfigfromdb('currency', $schema),
			'support'			=> readconfigfromdb('support', $schema),
			'admin'				=> readconfigfromdb('admin', $schema),
			'msgexpcleanupdays'	=> readconfigfromdb('msgexpcleanupdays', $schema),
		];

		return $return;
	}
}
