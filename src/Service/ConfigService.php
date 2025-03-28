<?php declare(strict_types=1);

namespace App\Service;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Redis;
use ReflectionClass;
use Symfony\Component\Validator\Exception\LogicException;

class ConfigService
{
	const PREFIX = 'config_';
	const TTL = 518400; // 60 days

	protected bool $local_cache_en = false;
	protected array $local_cache = [];

	public function __construct(
		protected Db $db,
		protected Redis $redis
	)
	{
		$this->local_cache_en = php_sapi_name() !== 'cli';
	}

	public function get_from_db(string $schema):array
	{
		$ary = [];

		$stmt = $this->db->prepare('select id, data
			from ' . $schema . '.config');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$ary[$row['id']] = json_decode($row['data'], true);
		}

		return $ary;
	}

	public function read_all(string $schema):array
	{
		$key = self::PREFIX . $schema;
		$data_json = $this->redis->get($key);

		if (is_string($data_json))
		{
			$data = json_decode($data_json, true);
		}
		else
		{
			$data = $this->get_from_db($schema);
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
		$this->redis->del($key);
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

	protected function set_val(string $id, $value, string $schema):void
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
				where id = ?',
				[$value, $user_id, $id],
				[Types::JSON, \PDO::PARAM_INT, \PDO::PARAM_STR]
			);
		}
		else
		{
			$this->db->executeStatement('update ' . $schema . '.config
				set data = \'null\'::jsonb, last_edit_by = ?
				where id = ?',
				[$user_id, $id],
				[\PDO::PARAM_INT, \PDO::PARAM_STR]
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
		if (count(array_filter(array_keys($value), 'is_string')) > 0)
		{
			throw new LogicException('String keys are not allowed in config arrays');
		}

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

	private function command_config_map_callback(
		CommandInterface $command,
		callable $callable,
		string $schema
	):void
	{
		$reflection_class = new ReflectionClass($command);

		foreach ($reflection_class->getProperties() as $property)
		{
			$attributes = $property->getAttributes(ConfigMap::class);
			$property_name = $property->getName();

			foreach ($attributes as $attribute)
			{
				$config_map = $attribute->newInstance();
				call_user_func($callable, $command, $property_name, $config_map, $schema);
			}
		}
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

	public function store_command(CommandInterface $command, string $schema):void
	{
		$callable = function(
			CommandInterface $command,
			string $property_name,
			ConfigMap $config_map,
			string $schema
		):void {
			$type = $config_map->type;
			$set = 'set_' . $type;
			$value = $command->$property_name;
			$value = $type === 'str' && !isset($value) ? '' : $value;
			// See #270, re-index to list
			$value = $type === 'ary' ? array_values($value) : $value;
			$this->$set($config_map->key, $value, $schema);
		};

		$this->command_config_map_callback($command, $callable, $schema);
	}
}
