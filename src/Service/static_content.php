<?php declare(strict_types=1);

namespace App\Service;

use service\xdb;
use App\Cnst\ConfigCnst;
use Predis\Client as Predis;
use service\config as config;

class static_content
{
	protected $xdb;
	protected $predis;
	protected $config;

	public function __construct(
		xdb $xdb,
		Predis $predis,
		config $config
	)
	{
		$this->predis = $predis;
		$this->xdb = $xdb;
		$this->config = $config;
	}

	public function exists(string $name, string $schema):bool
	{
		return 0 < $this->xdb->count('setting', $name, $schema);
	}

	public function get_uncached(string $key, string $schema):string
	{
		$row = $this->xdb->get('setting', $key, $schema);

		if ($row)
		{
			return $row['data']['value'];
		}

		return '';
	}

	public function set(string $name, string $schema, string $value):void
	{
		$this->xdb->set('setting', $name, ['value' => $value], $schema);
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

		$row = $this->xdb->get('setting', $key, $schema);

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
