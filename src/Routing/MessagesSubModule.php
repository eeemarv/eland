<?php declare(strict_types=1);

namespace App\Routing;

enum MessagesSubModule: string implements RouteModuleInterface
{
  case Categories = 'categories';
  case Expire = 'expire';
  case Images = 'images';

  public function get_parent(): RouteModuleInterface|null
  {
    return AppModule::Messages;
  }

  public function get_config_id(): string|null
  {
    return match ($this) {
      self::Categories => 'messages.fields.category.enabled',
      self::Expire => 'messages.fields.expires_at.enabled',
      self::Images => null,
    };    
  }
}
