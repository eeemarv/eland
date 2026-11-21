<?php declare(strict_types=1);

namespace App\Routing;

enum AccountsSubModule: string implements RouteModuleInterface
{
  case CodeAuto = 'code_auto';
  case Leaving = 'leaving';
  case Limits = 'limits';

  public function get_parent(): RouteModuleInterface|null
  {
    return UsersSubModule::Accounts;
  }

  public function get_config_id(): string|null
  {
    return match ($this) {
      self::CodeAuto => 'accounts.code.auto.enabled',
      self::Leaving => 'users.leaving.enabled',
      self::Limits => 'accounts.limits.enabled',
    };    
  }
}
