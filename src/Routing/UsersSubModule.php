<?php declare(strict_types=1);

namespace App\Routing;

enum UsersSubModule: string implements RouteModuleInterface
{
  case Accounts = 'accounts';
  case AdminComments = 'admin_comments';
  case Birthdate = 'birthdate';  
  case Comments = 'comments';
  case Contacts = 'contacts';
  case FullName = 'full_name';
  case Hobbies = 'hobbies';
  case Mollie = 'mollie';
  case New = 'new';
  case PeriodicMail = 'periodic_mail';
  case Postcode = 'postcode';
  case Tags = 'tags';

  public function get_parent(): RouteModuleInterface|null
  {
    return AppModule::Users;
  }

  public function get_config_id(): string|null
  {
    return match ($this) {
      self::Accounts => 'transactions.enabled',
      self::AdminComments => 'users.fields.admin_comments.enabled',
      self::Birthdate => 'users.fields.birthdate.enabled',
      self::Comments => 'users.fields.comments.enabled',
      self::Contacts => 'users.contacts.enabled',
      self::FullName => 'users.fields.full_name.enabled',
      self::Hobbies => 'users.fields.hobbies.enabled',
      self::Mollie => 'mollie.enabled',
      self::New => 'users.new.enabled',
      self::PeriodicMail => 'periodic_mail.enabled',
      self::Postcode => 'users.fields.postcode.enabled',
      self::Tags => 'users.tags.enabled',
    };    
  }
}
