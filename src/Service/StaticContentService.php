<?php declare(strict_types=1);

namespace App\Service;

use App\Service\XdbService;
use Doctrine\DBAL\Connection as Db;
use App\Cnst\ConfigCnst;
use Predis\Client as Predis;
use App\Service\ConfigService;

class StaticContentService
{
	protected $xdb_service;
	protected Db $db;
	protected Predis $predis;
	protected ConfigService $config_service;

	public function __construct(
		XdbService $xdb_service,
		Db $db,
		Predis $predis,
		ConfigService $config_service
	)
	{
		$this->db = $db;
		$this->predis = $predis;
		$this->xdb_service = $xdb_service;
		$this->config_service = $config_service;
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
