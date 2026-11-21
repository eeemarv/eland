<?php declare(strict_types=1);

namespace App\Routing;

enum TransactionsSubModule: string implements RouteModuleInterface
{
  case Invitations = 'invitations';
  case Limits = 'limits';
  case Mass = 'mass';
  case ServiceStuff = 'service_stuff';

  public function get_parent(): RouteModuleInterface|null
  {
    return AppModule::Transactions;
  }

  public function get_config_id(): string|null
  {
    return match ($this) {
      self::Invitations => 'transactions.invitations.enabled',
      self::Limits => 'accounts.limits.enabled',
      self::Mass => 'transactions.mass.enabled',
      self::ServiceStuff => 'transactions.fields.service_stuff.enabled',
    };    
  }
}
