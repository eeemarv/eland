<?php declare(strict_types=1);

namespace App\Service;

use App\Attributes\FeaturesMap;
use App\Command\CommandInterface;
use App\DTO\Schema;
use App\Repository\FeatureRepository;
use ReflectionClass;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * The features are settings that are not cached and
 * not needed on every page. For cached settings use
 * the ConfigService
 */
class FeaturesService
{
	private bool $local_cache_en = false;
	private array $local_cache = [];

	public function __construct(
    private readonly FeatureRepository $feature_repository,
	)
	{
		$this->local_cache_en = php_sapi_name() !== 'cli';
	}

	public function get_int(
    string $feature_id,
    Schema $schema,
  ):int|null
	{
    $schema_str = $schema->str();

		if (!isset($this->local_cache[$schema_str][$feature_id])
      || !$this->local_cache_en  
    )
		{
      $this->local_cache[$schema_str][$feature_id] 
        = $this->feature_repository->get(
          feature_id: $feature_id,
          schema: $schema,
        );
		}
		return  $this->local_cache[$schema_str][$feature_id];
	}

	public function get_bool(
    string $feature_id,
    Schema $schema,
  ):bool
	{
    $schema_str = $schema->str();

		if (!isset($this->local_cache[$schema_str][$feature_id])
      || !$this->local_cache_en  
    )
		{
      $this->local_cache[$schema_str][$feature_id] 
        = $this->feature_repository->get(
          feature_id: $feature_id,
          schema: $schema,
        );
		}
		return  $this->local_cache[$schema_str][$feature_id];
	}

	public function get_str(
    string $feature_id,
    Schema $schema,
  ):string
	{
    $schema_str = $schema->str();

		if (!isset($this->local_cache[$schema_str][$feature_id])
      || !$this->local_cache_en  
    )
		{
      $this->local_cache[$schema_str][$feature_id] 
        = $this->feature_repository->get(
          feature_id: $feature_id,
          schema: $schema,
        );
		}
		return  $this->local_cache[$schema_str][$feature_id];
	}

	public function get_ary(
    string $feature_id,
    Schema $schema,
  ):array
	{
    $schema_str = $schema->str();

		if (!isset($this->local_cache[$schema_str][$feature_id])
      || !$this->local_cache_en  
    )
		{
      $this->local_cache[$schema_str][$feature_id] 
        = $this->feature_repository->get(
          feature_id: $feature_id,
          schema: $schema,
        );
		}
		return  $this->local_cache[$schema_str][$feature_id];
	}

	public function set_int(
    string $feature_id,
    int|null $value,
    string $route,
    int|null $user_id,
    Schema $schema,
    string|null $comment = null,
  ):bool
	{
		$current_value = $this->get_int(
      feature_id: $feature_id,
      schema: $schema,
    );

		if ($current_value === $value)
		{
			return false;
		}

    $this->feature_repository->set_value(
      feature_id: $feature_id,
      new_data: $value,
      old_data: $current_value,
      route: $route,
      user_id: $user_id,
      schema: $schema,
      comment: $comment,
    );

    unset($this->local_cache[$schema->str()][$feature_id]);

    return true;
	}

	public function set_bool(
    string $feature_id,
    bool $value,
    string $route,
    int|null $user_id,
    Schema $schema,
    string|null $comment = null,
  ):bool
	{
		$current_value = $this->get_bool(
      feature_id: $feature_id,
      schema: $schema,
    );

		if ($current_value === $value)
		{
			return false;
		}

		$this->feature_repository->set_value(
      feature_id: $feature_id,
      new_data: $value,
      old_data: $current_value,
      route: $route,
      user_id: $user_id,
      schema: $schema,
      comment: $comment,
    );

    unset($this->local_cache[$schema->str()][$feature_id]);

    return true;
	}

	public function set_str(
    string $feature_id,
    string $value,
    string $route,
    int|null $user_id,
    Schema $schema,
    string|null $comment = null,
  ):bool
	{
		$current_value = $this->get_str(
      feature_id: $feature_id,
      schema: $schema,
    );

		if ($current_value === $value)
		{
			return false;
		}

		$this->feature_repository->set_value(
      feature_id: $feature_id,
      new_data: $value,
      old_data: $current_value,
      route: $route,
      user_id: $user_id,
      schema: $schema,
      comment: $comment,
    );

    unset($this->local_cache[$schema->str()][$feature_id]);

    return true;
	}

	public function set_ary(
    string $feature_id,
    array $value,
    string $route,
    int|null $user_id,
    Schema $schema,
    string|null $comment = null,
  ):bool
	{
		if (count(array_filter(array_keys($value), 'is_string')) > 0)
		{
			throw new LogicException('String keys are not allowed in config arrays');
		}

		$current_value = $this->get_ary(
      feature_id: $feature_id,
      schema: $schema,
    );

		if ($current_value === $value)
		{
			return false;
		}

		$this->feature_repository->set_value(
      feature_id: $feature_id,
      new_data: $value,
      old_data: $current_value,
      route: $route,
      user_id: $user_id,
      schema: $schema,
      comment: $comment,
    );

    unset($this->local_cache[$schema->str()][$feature_id]);

    return true;
	}

	private function command_features_map_callback(
		CommandInterface $command,
		callable $callable,
    string $route,
    int|null $user_id,
		Schema $schema
	):bool
	{
    $changed = false;
		$reflection_class = new ReflectionClass($command);

		foreach ($reflection_class->getProperties() as $property)
		{
			$attributes = $property->getAttributes(FeaturesMap::class);
			$property_name = $property->getName();

			foreach ($attributes as $attribute)
			{
				$features_map = $attribute->newInstance();
				$res = call_user_func($callable, $command, $property_name, $features_map, $route, $user_id, $schema);
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
    $route = '_dummy_';
    $user_id = null;

		$callable = function(
			CommandInterface $command,
			string $property_name,
			FeaturesMap $features_map,
      string $route, // not used in load
      int|null $user_id, // not used in load
			Schema $schema
		):void {
			$get = 'get_' . $features_map->type;
			$command->$property_name = $this->$get($features_map->key, $schema);
		};

		$this->command_features_map_callback(
      command: $command,
      callable: $callable,
      route: $route,
      user_id: $user_id,
      schema: $schema,
    );
	}

  /**
	 * @return bool value changed
	 */
	public function store_command(
    CommandInterface $command,
    string $route,
    int|null $user_id,
    Schema $schema,
    string|null $comment = null,
  ):bool
	{
		$callable = function(
			CommandInterface $command,
			string $property_name,
			FeaturesMap $features_map,
      string $route,
      int|null $user_id,
			Schema $schema,
		) use ($comment):bool {
			$type = $features_map->type;
			$set = 'set_' . $type;
			$value = $command->$property_name;
			$value = $type === 'str' && !isset($value) ? '' : $value;
			// See #270, re-index to list
			$value = $type === 'ary' ? array_values($value) : $value;
			return $this->$set(
        feature_id: $features_map->key,
        value: $value,
        route: $route,
        user_id: $user_id,
        schema: $schema,
        comment: $comment,
      );
		};

		return $this->command_features_map_callback(
      command: $command,
      callable: $callable,
      route: $route,
      user_id: $user_id,
      schema: $schema,
    );
	}
}
