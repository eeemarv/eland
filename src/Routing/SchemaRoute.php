<?php declare(strict_types=1);

namespace App\Routing;

use Attribute;
use Symfony\Component\Routing\Attribute\Route;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class SchemaRoute extends Route
{
  public function __construct(
    AccessLevel $access_level,
    string $path,
    string $name,
    RouteModuleInterface $module,
    bool|null $is_self = null,
    string|null $mode = null, 
    array $requirements = [],
    array $options = [],
    array $defaults = [],
    string|null $host = null,
    array|string $methods = [],
    array|string $schemes = [],
    string|null $condition = null,
    int|null $priority = null,
    string|null $locale = null,
    string|null $format = null,
    bool|null $utf8 = null,
    bool|null $stateless = null,
    string|null $env = null,
  ) 
  {
    $path_prefix = '/{schema}/';

    $defaults['_access_level'] = $access_level->value;

    if ($access_level !== AccessLevel::Anonymous) 
    {
      $path_prefix .= '{role_short}/';
      $allowed_role_short_ary = $access_level->get_allowed_role_short_ary();     
      $requirements['role_short'] = implode('|', $allowed_role_short_ary);
    }

    $path = $path_prefix . ltrim($path, '/');

    if (isset($module) && is_string($module))
    {
      $defaults['_module'] = $module;
    }
    else if (isset($module) && $module instanceof RouteModuleInterface)
    {
      $module_chain = [];
      $module_config_ids = [];

      for ($current = $module; $current !== null; $current = $current->get_parent()) 
      {
        $module_chain[] = (string) $current->value;
        $config_id = $current->get_config_id();
        if (isset($config_id)) 
        {
          $module_config_ids[$config_id] = (string) $current->value;
        }
      }

      $defaults['_module_chain'] = array_reverse($module_chain);
      $defaults['_module_config_ids'] = array_reverse($module_config_ids, true);
      
      if (isset($defaults['_module_chain'][0]))
      {
        $defaults['_module'] = $defaults['_module_chain'][0];
      }

      if (isset($defaults['_module_chain'][1]))
      {
        $defaults['_sub_module'] = $defaults['_module_chain'][0];
      }
    }

    if (isset($is_self))
    {
      $defaults['_is_self'] = $is_self;
    }

    if (isset($mode))
    {
      $defaults['_mode'] = $mode;
    }

    parent::__construct(
      path: $path,
      name: $name,
      requirements: $requirements,
      options: $options,
      defaults: $defaults,
      host: $host,
      methods: $methods,
      schemes: $schemes,
      condition: $condition,
      priority: $priority,
      locale: $locale,
      format: $format,
      utf8: $utf8,
      stateless: $stateless,
      env: $env,
    );
  }
}
