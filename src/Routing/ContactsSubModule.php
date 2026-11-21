<?php declare(strict_types=1);

namespace App\Routing;

enum ContactsSubModule: string implements RouteModuleInterface
{
  case ContactTypes = 'contact_types';

  public function get_parent(): RouteModuleInterface|null
  {
    return UsersSubModule::Contacts;
  }

  public function get_config_id(): string|null
  {
    return match ($this) {
      self::ContactTypes => null,
    };    
  }
}
