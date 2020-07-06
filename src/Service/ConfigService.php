<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use App\Cnst\ConfigCnst;
use Doctrine\DBAL\Types\Types;
use Predis\Client as Predis;
use Symfony\Component\Validator\Exception\LogicException;

class ConfigService
{
	const PREFIX = 'config_';
	const TTL = 518400; // 60 days
	const PREFIX_STATIC_CONTENT = 'static_content_';
	const TTL_STATIC_CONTENT = 518400;

	protected Db $db;
	protected Predis $predis;

	protected bool $local_cache_en = false;
	protected bool $in_transaction = false;
	protected array $load_ary = [];
	protected array $local_cache = [];
	protected array $st_local_cache = [];

	public function __construct(
		Db $db,
		Predis $predis
	)
	{
		$this->predis = $predis;
		$this->db = $db;
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
		$key = self::PREFIX . $schema;
		$this->predis->set($key, json_encode($this->load_ary));
		$this->predis->expire($key, self::TTL);
		return $this->load_ary;
	}

	public function read_all(string $schema):array
	{
		$key = self::PREFIX . $schema;
		$data_json = $this->predis->get($key);

		if (isset($data_json))
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
		$key = self::PREFIX . $schema;
		$this->predis->del($key);
		unset($this->local_cache[$schema]);
		$st_key = self::PREFIX_STATIC_CONTENT . $schema;
		$this->predis->del($st_key);
		unset($this->st_local_cache[$schema]);
	}

	public function get_int(string $path, string $schema):?int
	{
		if (!isset($this->local_cache[$schema]))
		{
			return $this->read_all($schema)[$path];
		}
		return  $this->local_cache[$schema][$path];
	}

	public function get_bool(string $path, string $schema):bool
	{
		if (!isset($this->local_cache[$schema]))
		{
			return $this->read_all($schema)[$path];
		}
		return  $this->local_cache[$schema][$path];
	}

	public function get_str(string $path, string $schema):string
	{
		if (!isset($this->local_cache[$schema]))
		{
			return $this->read_all($schema)[$path];
		}
		return  $this->local_cache[$schema][$path];
	}

	public function get_ary(string $path, string $schema):array
	{
		if (!isset($this->local_cache[$schema]))
		{
			return $this->read_all($schema)[$path];
		}
		return  $this->local_cache[$schema][$path];
	}

	protected function set_val(string $key, $value, string $schema):void
	{
		$path_ary = explode('.', $key);

		foreach($path_ary as $p)
		{
			if (!preg_match('/^[a-z_]+$/', $p))
			{
				throw new LogicException('Unacceptable path');
			}
		}

		$id = array_shift($path_ary);
		$path = implode(',', $path_ary);

		if ($path === '')
		{
			throw new LogicException('Config path not set for id ' . $id);
		}

		if ($id === 'static_content')
		{
			$st_id = array_shift($path_ary);
			$st_path = implode(',', $path_ary);

			if ($st_path === '')
			{
				throw new LogicException('static content path not set for id ' . $st_id);
			}

			if (isset($value))
			{
				$this->db->executeUpdate('update ' . $schema . '.static_content
					set data = jsonb_set(data, \'{' . $st_path . '}\',  ?)
					where id = ?',
					[$value, $st_id],
					[Types::JSON, \PDO::PARAM_STR]
				);
			}
			else
			{
				$this->db->executeUpdate('update ' . $schema . '.static_content
					set data = jsonb_set(data, \'{' . $st_path . '}\',  \'null\'::jsonb)
					where id = ?',
					[$st_id],
					[\PDO::PARAM_STR]
				);
			}

			return;
		}

		if (isset($value))
		{
			$this->db->executeUpdate('update ' . $schema . '.config
				set data = jsonb_set(data, \'{' . $path . '}\',  ?)
				where id = ?',
				[$value, $id],
				[Types::JSON, \PDO::PARAM_STR]
			);
		}
		else
		{
			$this->db->executeUpdate('update ' . $schema . '.config
				set data = jsonb_set(data, \'{' . $path . '}\',  \'null\'::jsonb)
				where id = ?',
				[$id],
				[\PDO::PARAM_STR]
			);
		}

		$this->clear_cache($schema);
	}

	public function set_int(string $key, ?int $value, string $schema):void
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

	public function get(string $key, string $schema):string
	{
		$path = ConfigCnst::INPUTS[$key]['path'];

		if (strpos($path, 'static_content.') === 0)
		{
			[$table, $id, $field] = explode('.', $path);

			if (isset($this->st_local_cache[$schema][$id]))
			{
				return $this->st_local_cache[$schema][$id][$field];
			}

			$st_key = self::PREFIX_STATIC_CONTENT . $schema;
			$data_json = $this->predis->hget($st_key, $id);

			if (!isset($data_json) || !$data_json)
			{
				error_log('get id ' . $id . ' field ' . $field);

				$data_json = $this->db->fetchColumn('select data
					from ' . $schema . '.static_content
					where id = ?', [$id]);

				$this->predis->hset($st_key, $id, $data_json);
				$this->predis->expire($st_key, self::TTL_STATIC_CONTENT);
			}

			$data = json_decode($data_json, true);

			if ($this->local_cache_en)
			{
				$this->st_local_cache[$schema][$id] = $data;
			}

			return $data[$field];
		}

		if (!isset($this->local_cache[$schema]))
		{
			$ret = $this->read_all($schema)[$path];
		}
		else
		{
			$ret = $this->local_cache[$schema][$path];
		}

		error_log($path . ' ' . json_encode($ret));

		$ret = (string) $ret;

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
