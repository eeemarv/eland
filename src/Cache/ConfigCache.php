<?php declare(strict_types=1);

namespace App\Cache;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use ReflectionClass;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ConfigCache
{
	const CACHE_PREFIX = 'config.';
	const CACHE_TTL = 86400;
	const CACHE_BETA = 1;

	protected bool $local_cache_en = false;
	protected array $load_ary = [];
	protected array $local_cache = [];

	public function __construct(
		protected Db $db,
		protected TagAwareCacheInterface $cache,
	)
	{
		$this->local_cache_en = php_sapi_name() !== 'cli';
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

			if (array_is_list($value))
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

		$fid = $this->db->fetchOne('select id
			from ' . $schema . '.config
			where flattened = \'t\'::bool
			limit 1');

		if ($fid !== false)
		{
			$stmt = $this->db->prepare('select id, data
				from ' . $schema . '.config
				where flattened = \'t\'::bool');

			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$this->load_ary[$row['id']] = json_decode($row['data'], true);
			}

			return $this->load_ary;
		}

		$stmt = $this->db->prepare('select id, data
			from ' . $schema . '.config
			where flattened = \'f\'::bool');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$this->flatten_load_ary($row['id'], json_decode($row['data'], true));
		}

		return $this->load_ary;
	}

	public function read_all(string $schema):array
	{
		$data = $this->cache->get(self::CACHE_PREFIX . $schema, function(ItemInterface $item) use ($schema){
			$item->expiresAfter(self::CACHE_TTL);
			$item->tag(['config']);
			return $this->build_cache_from_db($schema);
		}, self::CACHE_BETA);

		if ($this->local_cache_en)
		{
			$this->local_cache[$schema] = $data;
		}

		$fid = $this->db->fetchOne('select id
			from ' . $schema . '.config
			where flattened = \'t\'::bool
			limit 1');

		if ($fid !== false)
		{
			return $data;
		}

		$stmt = $this->db->prepare('select id, created_at, last_edit_at, edit_count
			from ' . $schema . '.config
			where flattened = \'f\'::bool');

		$res = $stmt->executeQuery();

		$e_data = [];

		while ($row = $res->fetchAssociative())
		{
			$e_data[$row['id']] = $row;
		}

		foreach ($data as $id => $value)
		{
			[$oid] = explode('.', $id);

			$ins = [
				'id'	=> $id,
				'data'	=> json_encode($value),
				'flattened'	=> 't',
				'created_at'	=> $e_data[$oid]['created_at'],
				'last_edit_at'	=> $e_data[$oid]['last_edit_at'],
				'edit_count'	=> $e_data[$oid]['edit_count']
			];
			$this->db->insert($schema . '.config', $ins);
		}

		return $data;
	}

	public function clear_cache(string $schema):void
	{
		$this->cache->delete(self::CACHE_PREFIX . $schema);
		unset($this->local_cache[$schema]);
	}

	public function get_int(string $path, string $schema):null|int
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

	protected function set_val(string $id, mixed $value, string $schema):void
	{
		$user_id = null;

		$path_ary = explode('.', $id);

		foreach($path_ary as $p)
		{
			if (!preg_match('/^[a-z_]+$/', $p))
			{
				throw new LogicException('Unacceptable path');
			}
		}

		if (isset($value))
		{
			$this->db->executeStatement('update ' . $schema . '.config
				set data = ?, last_edit_by = ?
				where id = ?
					and flattened = \'t\'::bool',
				[$value, $user_id, $id],
				[Types::JSON, \PDO::PARAM_INT, \PDO::PARAM_STR]
			);
		}
		else
		{
			$this->db->executeStatement('update ' . $schema . '.config
				set data = \'null\'::jsonb, last_edit_by = ?
				where id = ?
					and flattened = \'t\'::bool',
				[$user_id, $id],
				[\PDO::PARAM_INT, \PDO::PARAM_STR]
			);
		}

		$this->clear_cache($schema);
		return;
	}

	public function set_int(string $key, null|int $value, string $schema):bool
	{
		$current_value = $this->get_int($key, $schema);

		if ($current_value === $value)
		{
			return false;
		}

		$this->set_val($key, $value, $schema);
		return true;
	}

	public function set_bool(string $key, bool $value, string $schema):bool
	{
		$current_value = $this->get_bool($key, $schema);

		if ($current_value === $value)
		{
			return false;
		}

		$this->set_val($key, $value, $schema);
		return true;
	}

	public function set_str(string $key, string $value, string $schema):bool
	{
		$current_value = $this->get_str($key, $schema);

		if ($current_value === $value)
		{
			return false;
		}

		$this->set_val($key, $value, $schema);
		return true;
	}

	public function set_ary(string $key, array $value, string $schema):bool
	{
		if (count(array_filter(array_keys($value), 'is_string')) > 0)
		{
			throw new LogicException('String keys are not allowed in config arrays');
		}

		$current_value = $this->get_ary($key, $schema);

		if ($current_value === $value)
		{
			return false;
		}

		$this->set_val($key, $value, $schema);
		return true;
	}

	public function get_intersystem_en(string $schema):bool
	{
		return $this->get_bool('transactions.enabled', $schema)
			&& $this->get_bool('transactions.currency.timebased_en', $schema)
			&& $this->get_bool('intersystem.enabled', $schema);
	}

	public function get_new_user_treshold(string $schema):\DateTimeImmutable
	{
		$new_user_days = $this->get_int('users.new.days', $schema);
		$new_user_treshold = time() -  ($new_user_days * 86400);
		return \DateTimeImmutable::createFromFormat('U', (string) $new_user_treshold);
	}

	private function command_config_map_callback(
		CommandInterface $command,
		callable $callable,
		string $schema
	):bool
	{
		$changed = false;
		$reflection_class = new ReflectionClass($command);

		foreach ($reflection_class->getProperties() as $property)
		{
			$attributes = $property->getAttributes(ConfigMap::class);
			$property_name = $property->getName();

			foreach ($attributes as $attribute)
			{
				$config_map = $attribute->newInstance();
				$res = call_user_func($callable, $command, $property_name, $config_map, $schema);
				if ($res === true)
				{
					$changed = true;
				}
			}
		}
		return $changed;
	}

	public function load_command(CommandInterface $command, string $schema):void
	{
		$callable = function(
			CommandInterface $command,
			string $property_name,
			ConfigMap $config_map,
			string $schema
		):void {
			$get = 'get_' . $config_map->type;
			$command->$property_name = $this->$get($config_map->key, $schema);
		};

		$this->command_config_map_callback($command, $callable, $schema);
	}

	/**
	 * @return bool value changed
	 */
	public function store_command(CommandInterface $command, string $schema):bool
	{
		$callable = function(
			CommandInterface $command,
			string $property_name,
			ConfigMap $config_map,
			string $schema
		):bool {
			$type = $config_map->type;
			$set = 'set_' . $type;
			$value = $command->$property_name;
			$value = $type === 'str' && !isset($value) ? '' : $value;
			// See #270, re-index to list
			$value = $type === 'ary' ? array_values($value) : $value;
			return $this->$set($config_map->key, $value, $schema);
		};

		return $this->command_config_map_callback($command, $callable, $schema);
	}
}
