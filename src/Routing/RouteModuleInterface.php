<?php declare(strict_types=1);

namespace App\Routing;

interface RouteModuleInterface
{
  public function get_parent(): RouteModuleInterface|null;
  public function get_config_id(): string|null;
}
