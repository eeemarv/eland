<?php declare(strict_types=1);

namespace App\Service;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use App\DTO\Schema;
use App\Repository\ConfigRepository;
use ReflectionClass;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ConfigService
{
	const CACHE_PREFIX = 'config.';
	const CACHE_TTL = 86400;
	const CACHE_BETA = 1;

	protected bool $local_cache_en = false;
	protected array $local_cache = [];

	public function __construct(
    private readonly ConfigRepository $config_repository,
    private readonly TagAwareCacheInterface $cache,
	)
	{
		$this->local_cache_en = php_sapi_name() !== 'cli';
	}

	public function read_all(
    Schema $schema,
  ):array
	{
		$data = $this->cache->get(self::CACHE_PREFIX . $schema->str(), function(ItemInterface $item) use ($schema){
			$item->expiresAfter(self::CACHE_TTL);
			$item->tag(['config']);
			return $this->config_repository->get_all($schema);
		}, self::CACHE_BETA);

		if ($this->local_cache_en)
		{
			$this->local_cache[$schema->str()] = $data;
		}

		return $data;
	}

	public function clear_cache(
    Schema $schema,
  ):void
	{
		$this->cache->delete(self::CACHE_PREFIX . $schema->str());
		unset($this->local_cache[$schema->str()]);
	}

	public function get_int(
    string $config_id,
    Schema $schema,
  ):int|null
	{
		if (!isset($this->local_cache[$schema->str()]))
		{
			return $this->read_all(
        schema: $schema,
      )[$config_id];
		}
		return  $this->local_cache[$schema->str()][$config_id];
	}

	public function get_bool(
    string $config_id,
    Schema $schema,
  ):bool
	{
		if (!isset($this->local_cache[$schema->str()]))
		{
			return $this->read_all(
        schema: $schema,
      )[$config_id];
		}
		return  $this->local_cache[$schema->str()][$config_id];
	}

	public function get_str(
    string $config_id,
    Schema $schema,
  ):string
	{
		if (!isset($this->local_cache[$schema->str()]))
		{
			return $this->read_all(
        schema: $schema,
      )[$config_id];
		}
		return  $this->local_cache[$schema->str()][$config_id];
	}

	public function get_ary(
    string $config_id,
    Schema $schema,
  ):array
	{
		if (!isset($this->local_cache[$schema->str()]))
		{
			return $this->read_all(
        schema: $schema,
      )[$config_id];
		}
		return  $this->local_cache[$schema->str()][$config_id];
	}

	public function set_int(
    string $config_id,
    int|null $value,
    int|null $user_id,
    Schema $schema,
  ):bool
	{
		$current_value = $this->get_int(
      config_id: $config_id,
      schema: $schema,
    );

		if ($current_value === $value)
		{
			return false;
		}

    $this->config_repository->set_value(
      config_id: $config_id,
      value: $value,
      user_id: $user_id,
      schema: $schema,
    );

    $this->clear_cache($schema);

    return true;
	}

	public function set_bool(
    string $config_id,
    bool $value,
    int|null $user_id,
    Schema $schema,
  ):bool
	{
		$current_value = $this->get_bool(
      config_id: $config_id,
      schema: $schema,
    );

		if ($current_value === $value)
		{
			return false;
		}

		$this->config_repository->set_value(
      config_id: $config_id,
      value: $value,
      user_id: $user_id,
      schema: $schema,
    );

    $this->clear_cache($schema);

    return true;
	}

	public function set_str(
    string $config_id,
    string $value,
    int|null $user_id,
    Schema $schema,
  ):bool
	{
		$current_value = $this->get_str(
      config_id: $config_id,
      schema: $schema,
    );

		if ($current_value === $value)
		{
			return false;
		}

		$this->config_repository->set_value(
      config_id: $config_id,
      value: $value,
      user_id: $user_id,
      schema: $schema,
    );

    $this->clear_cache($schema);

    return true;
	}

	public function set_ary(
    string $config_id,
    array $value,
    int|null $user_id,
    Schema $schema,
  ):bool
	{
		if (count(array_filter(array_keys($value), 'is_string')) > 0)
		{
			throw new LogicException('String keys are not allowed in config arrays');
		}

		$current_value = $this->get_ary(
      config_id: $config_id,
      schema: $schema,
    );

		if ($current_value === $value)
		{
			return false;
		}

		$this->config_repository->set_value(
      config_id: $config_id,
      value: $value,
      user_id: $user_id,
      schema: $schema,
    );

    $this->clear_cache($schema);

    return true;
	}

	public function get_intersystem_en(
    Schema $schema,
  ):bool
	{
		return $this->get_bool('transactions.currency.timebased_en', $schema)
			&& $this->get_bool('intersystem.enabled', $schema);
	}

	public function get_new_user_treshold(
    Schema $schema,
  ):\DateTimeImmutable
	{
		$new_user_days = $this->get_int('users.new.days', $schema);
		$new_user_treshold = time() -  ($new_user_days * 86400);
		return \DateTimeImmutable::createFromFormat('U', (string) $new_user_treshold);
	}

	private function command_config_map_callback(
		CommandInterface $command,
		callable $callable,
    int|null $user_id,
		Schema $schema
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
				$res = call_user_func($callable, $command, $property_name, $config_map, $user_id, $schema);
        if ($res)
        {
          $changed = true;
        }
			}
		}
    return $changed;
	}

	public function load_command(
    CommandInterface $command,
    Schema $schema,
  ):void
	{
    $user_id = null;

		$callable = function(
			CommandInterface $command,
			string $property_name,
			ConfigMap $config_map,
      int|null $user_id, // not used in load
			Schema $schema
		):void {
			$get = 'get_' . $config_map->type;
			$command->$property_name = $this->$get($config_map->key, $schema);
		};

		$this->command_config_map_callback($command, $callable, $user_id, $schema);
	}

  /**
	 * @return bool value changed
	 */
	public function store_command(
    CommandInterface $command,
    int|null $user_id,
    Schema $schema
  ):bool
	{
		$callable = function(
			CommandInterface $command,
			string $property_name,
			ConfigMap $config_map,
      int|null $user_id,
			Schema $schema
		):bool {
			$type = $config_map->type;
			$set = 'set_' . $type;
			$value = $command->$property_name;
			$value = $type === 'str' && !isset($value) ? '' : $value;
			// See #270, re-index to list
			$value = $type === 'ary' ? array_values($value) : $value;
			return $this->$set(
        config_id: $config_map->key,
        value: $value,
        user_id: $user_id,
        schema: $schema,
      );
		};

		return $this->command_config_map_callback(
      command: $command,
      callable: $callable,
      user_id: $user_id,
      schema: $schema,
    );
	}
}
