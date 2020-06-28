<?php declare(strict_types=1);

namespace App\Service;

use App\Service\XdbService;
use Doctrine\DBAL\Connection as Db;
use App\Cnst\ConfigCnst;
use Doctrine\DBAL\Types\Types;
use Predis\Client as Predis;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ConfigService
{
	const REDIS_KEY_PREFIX = 'config_';
	const TTL = 518400; // 60 days
	const TRANS_STR = [
	];

	protected XdbService $xdb_service;
	protected Db $db;
	protected Predis $predis;

	protected bool $local_cache_en = false;
	protected bool $in_transaction = false;
	protected array $load_ary = [];
	protected array $local_cache = [];

	public function __construct(
		XdbService $xdb_service,
		Db $db,
		Predis $predis
	)
	{
		$this->predis = $predis;
		$this->db = $db;
		$this->xdb_service = $xdb_service;
		$this->local_cache_en = php_sapi_name() === 'cli' ? false : true;
	}

	protected function is_sequential_ary(array $ary):bool
	{
		$i = 0;
		foreach($ary as $k => $v)
		{
			if ($k !== $i)
			{
				return false;
			}
			$i++;
		}
		return true;
	}

	protected function flatten_load_ary(string $prefix, array $ary):void
	{
		foreach($ary as $key => $value)
		{
			$id = $prefix . '.' . $key;

			if (!is_array($value))
			{
				$this->load_ary[$id] = $value;
				continue;
			}

			if ($this->is_sequential_ary($value))
			{
				$this->load_ary[$id] = $value;
				continue;
			}

			if ($key === 'access_options')
			{
				$this->load_ary[$id] = $value;
			}

			$this->flatten_load_ary($id, $value);
		}
	}

	public function build_cache_from_db(string $schema):array
	{
		$this->load_ary = [];
		$stmt = $this->db->executeQuery('select id, data
			from ' . $schema . '.config');
		while($row = $stmt->fetch())
		{
			$this->flatten_load_ary($row['id'], json_decode($row['data'], true));
		}
		$key = self::REDIS_KEY_PREFIX . $schema;
		$this->predis->set($key, json_encode($this->load_ary));
		$this->predis->expire($key, self::TTL);
		return $this->load_ary;
	}

	public function read_all(string $schema):array
	{
		$key = self::REDIS_KEY_PREFIX . $schema;
		$data_json = $this->predis->get($key);
		if (isset($data))
		{
			$data = json_decode($data_json, true);
		}
		else
		{
			$data = $this->build_cache_from_db($schema);
		}

		if ($this->local_cache_en)
		{
			$this->local_cache[$schema] = $data;
		}

		return $data;
	}

	public function clear_cache(string $schema):void
	{
		$key = self::REDIS_KEY_PREFIX . $schema;
		$this->predis->del($key);
		unset($this->local_cache[$schema]);
	}

	public function get_int(string $key, string $schema):?int
	{
		if (!isset($this->local_cache[$schema]))
		{
			return $this->read_all($schema)[$key];
		}
		return  $this->local_cache[$schema][$key];
	}

	public function get_bool(string $key, string $schema):bool
	{
		if (!isset($this->local_cache[$schema]))
		{
			return $this->read_all($schema)[$key];
		}
		return  $this->local_cache[$schema][$key];
	}

	public function get_str(string $key, string $schema):string
	{
		if (!isset($this->local_cache[$schema]))
		{
			return $this->read_all($schema)[$key];
		}
		return  $this->local_cache[$schema][$key];
	}

	public function get_ary(string $key, string $schema):array
	{
		if (!isset($this->local_cache[$schema]))
		{
			return $this->read_all($schema)[$key];
		}
		return  $this->local_cache[$schema][$key];
	}

	protected function set_val(string $key, $value, string $schema):void
	{
		$path_ary = explode('.', $key);

		foreach($path_ary as $p)
		{
			if (!preg_match('/^[a-z_]+$/', $p))
			{
				throw new BadRequestHttpException('Unacceptable path');
			}
		}

		$id = array_shift($path_ary);
		$path = implode(',', $path_ary);

		if ($path === '')
		{
			$this->db->executeUpdate('update ' . $schema . '.config
				set data = ?
				where id = ?',
				[$value, $id],
				[Types::JSON, \PDO::PARAM_STR]
			);
			$this->clear_cache($schema);
			return;
		}

		$this->db->executeUpdate('update ' . $schema . '.config
			set data = jsonb_set(data, \'{' . $path . '}\',  ?)
			where id = ?',
			[$value, $id],
			[Types::JSON, \PDO::PARAM_STR]
		);
		$this->clear_cache($schema);
	}

	public function set_int(string $key, int $value, string $schema):void
	{
		$this->set_val($key, $value, $schema);
	}

	public function set_bool(string $key, bool $value, string $schema):void
	{
		$this->set_val($key, $value, $schema);
	}

	public function set_str(string $key, string $value, string $schema):void
	{
		$this->set_val($key, $value, $schema);
	}

	public function set_ary(string $key, array $value, string $schema):void
	{
		$this->set_val($key, $value, $schema);
	}

	public function set(string $name, string $schema, string $value):void
	{
		$this->xdb_service->set('setting', $name, ['value' => $value], $schema);
		$this->predis->del($schema . '_config_' . $name);
	}

	public function get(string $key, string $schema):string
	{
		$path = ConfigCnst::INPUTS[$key]['path'];

		if (strpos($path, 'static_content.') === 0)
		{
			[$table, $id, $field] = explode('.', $path);

			$json = $this->db->fetchAssoc('select data
				from ' . $schema . '.static_content
				where id = ?', [$id],
				[\PDO::PARAM_STR])['data'];

			return json_decode($json, true)[$field];
		}

		if (!isset($this->local_cache[$schema]))
		{
			$ret = $this->read_all($schema)[$path];
		}
		else
		{
			$ret = $this->local_cache[$schema][$path];
		}

		/*

		$row = $this->xdb_service->get('setting', $key, $schema);

		if ($row)
		{
			$value = (string) $row['data']['value'];
		}
		else if (isset(ConfigCnst::INPUTS[$key]['default']))
		{
			$value = ConfigCnst::INPUTS[$key]['default'];
		}

		if (!isset($value))
		{
			$value = '';
		}
		*/

		if (in_array($path, ['mail.addresses.support', 'mail.addresses.admin']))
		{
			$ret = implode(',', $ret);
		}

		$ret = (string) $ret;

		/*
		if ($value !== $ret)
		{
			error_log('MISMATCH -- key : ' . $key . ' xdb: ' . $value . ' cnf: ' . $ret);
		}
		*/

		return $ret;
	}

	public function get_intersystem_en(string $schema):bool
	{
		return $this->get('template_lets', $schema)
			&& $this->get('interlets_en', $schema);
	}

	public function get_new_user_treshold(string $schema):int
	{
		$new_user_days = (int) $this->get('newuserdays', $schema);
		return time() -  ($new_user_days * 86400);
	}
}
