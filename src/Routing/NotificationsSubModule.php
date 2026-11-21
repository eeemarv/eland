<?php declare(strict_types=1);

namespace App\Routing;

enum NotificationsSubModule: string implements RouteModuleInterface
{
  case Email = 'email';
  case WebPush = 'web_push';

  public function get_parent(): RouteModuleInterface|null
  {
    return AppModule::Users;
  }

  public function get_config_id(): string|null
  {
    return match ($this) {
      self::Email => 'notifications.email.enabled',
      self::WebPush => 'notifications.webpush.enabled',
    };    
  }
}
