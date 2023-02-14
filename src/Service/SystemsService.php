<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SystemsService
{
	protected array $schemas = [];
	protected array $systems = [];

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

	public function __construct(
		protected Db $db,
		#[Autowire('%env(LEGACY_ELAND_ORIGIN_PATTERN)%')]
		protected string $env_legacy_eland_origin_pattern
	)
	{
		$stmt = $this->db->prepare('select schema_name
			from information_schema.schemata');

		$res = $stmt->executeQuery();

		while($row = $res->fetchAssociative())
		{
			$schema = $row['schema_name'];

			if (isset(self::IGNORE[$schema]))
			{
				continue;
			}

			if (str_starts_with($schema, 'pg_'))
			{
				continue;
			}

			$system = $schema;

			$this->schemas[$system] = $schema;
			$this->systems[$schema] = $system;
		}
	}

	public function get_legacy_eland_origin(string $schema):string
	{
		if (!isset($this->systems[$schema]))
		{
			return '';
		}

		return str_replace('_', $this->systems[$schema], $this->env_legacy_eland_origin_pattern);
	}

	public function get_schema_from_legacy_eland_origin(string $origin):string
	{
		$host = strtolower(parse_url($origin, PHP_URL_HOST) ?? '');

		if (!$host)
		{
			return '';
		}

		[$system] = explode('.', $host);

		return $this->schemas[$system] ?? '';
 	}

	public function get_schemas():array
	{
		return $this->schemas;
	}

	public function get_systems():array
	{
		return $this->systems;
	}

	public function get_schema(string $system):string
	{
		return $this->schemas[$system] ?? '';
	}

	public function get_system(string $schema):string
	{
		return $this->systems[$schema] ?? '';
	}

	public function count():int
	{
		return count($this->schemas);
	}
}
