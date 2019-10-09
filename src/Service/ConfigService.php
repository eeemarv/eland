<?php declare(strict_types=1);

namespace App\Service;

use App\Service\XdbService;
use App\Cnst\ConfigCnst;
use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;

class ConfigService
{
	protected $db;
	protected $xdb_service;
	protected $predis;
	protected $is_cli;

	public function __construct(
		Db $db,
		XdbService $xdb_service,
		Predis $predis
	)
	{
		$this->predis = $predis;
		$this->db = $db;
		$this->xdb_service = $xdb_service;
		$this->is_cli = php_sapi_name() === 'cli' ? true : false;
	}

	public function exists(string $name, string $schema):bool
	{
		return 0 < $this->xdb_service->count('setting', $name, $schema);
	}

	public function get_uncached(string $key, string $schema):string
	{
		$row = $this->xdb_service->get('setting', $key, $schema);

		if ($row)
		{
			return $row['data']['value'];
		}

		return '';
	}

	public function set(string $name, string $schema, string $value):void
	{
		$this->xdb_service->set('setting', $name, ['value' => $value], $schema);
		$this->predis->del($schema . '_config_' . $name);

		// here no update for eLAS database
	}

	public function get(string $key, string $schema):string
	{
		if (isset($this->local_cache[$schema][$key]) && !$this->is_cli)
		{
			return $this->local_cache[$schema][$key];
		}

		$redis_key = $schema . '_config_' . $key;

		if ($this->predis->exists($redis_key))
		{
			return $this->local_cache[$schema][$key] = $this->predis->get($redis_key);
		}

		$row = $this->xdb_service->get('setting', $key, $schema);

		if ($row)
		{
			$value = (string) $row['data']['value'];
		}
		else if (isset(ConfigCnst::INPUTS[$key]['default']))
		{
			$value = ConfigCnst::INPUTS[$key]['default'];
		}

		if (isset($value))
		{
			$this->predis->set($redis_key, $value);
			$this->predis->expire($redis_key, 2592000);
			$this->local_cache[$schema][$key] = $value;
		}
		else
		{
			$value = '';
		}

		return $value;
	}
}
