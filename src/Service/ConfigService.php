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

	protected bool $local_cache_en = false;
	protected array $load_ary = [];
	protected array $local_cache = [];

	public function __construct(
		protected Db $db,
		protected Predis $predis
	)
	{
		$this->local_cache_en = php_sapi_name() !== 'cli';
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

		if (isset($value))
		{
			$this->db->executeStatement('update ' . $schema . '.config
				set data = jsonb_set(data, \'{' . $path . '}\',  ?)
				where id = ?',
				[$value, $id],
				[Types::JSON, \PDO::PARAM_STR]
			);
		}
		else
		{
			$this->db->executeStatement('update ' . $schema . '.config
				set data = jsonb_set(data, \'{' . $path . '}\',  \'null\'::jsonb)
				where id = ?',
				[$id],
				[\PDO::PARAM_STR]
			);
		}

		$this->clear_cache($schema);
		return;
	}

	public function set_int(string $key, ?int $value, string $schema):void
	{
		$current_value = $this->get_int($key, $schema);

		if ($current_value === $value)
		{
			return;
		}

		$this->set_val($key, $value, $schema);
	}

	public function set_bool(string $key, bool $value, string $schema):void
	{
		$current_value = $this->get_bool($key, $schema);

		if ($current_value === $value)
		{
			return;
		}

		$this->set_val($key, $value, $schema);
	}

	public function set_str(string $key, string $value, string $schema):void
	{
		$current_value = $this->get_str($key, $schema);

		if ($current_value === $value)
		{
			return;
		}

		$this->set_val($key, $value, $schema);
	}

	public function set_ary(string $key, array $value, string $schema):void
	{
		$current_value = $this->get_ary($key, $schema);

		if ($current_value === $value)
		{
			return;
		}

		$this->set_val($key, $value, $schema);
	}

	public function get_intersystem_en(string $schema):bool
	{
		return $this->get_bool('transactions.currency.timebased_en', $schema)
			&& $this->get_bool('intersystem.enabled', $schema);
	}

	public function get_new_user_treshold(string $schema):\DateTimeImmutable
	{
		$new_user_days = $this->get_int('users.new.days', $schema);
		$new_user_treshold = time() -  ($new_user_days * 86400);
		return \DateTimeImmutable::createFromFormat('U', (string) $new_user_treshold);
	}
}
