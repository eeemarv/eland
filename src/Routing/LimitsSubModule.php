<?php declare(strict_types=1);

namespace App\Routing;

enum LimitsSubModule: string implements RouteModuleInterface
{
  case AutoMinLimit = 'auto_min_limit';

  public function get_parent(): RouteModuleInterface|null
  {
    return TransactionsSubModule::Limits;
  }

  public function get_config_id(): string|null
  {
    return match ($this) {
      self::AutoMinLimit => 'accounts.limits.auto_min.enabled',
    };    
  }
}
