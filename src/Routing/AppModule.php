<?php declare(strict_types=1);

namespace App\Routing;

enum AppModule: string implements RouteModuleInterface
{
  case Chat = 'chat';
  case Cms = 'cms';
  case Config = 'config';
  case ContactForm = 'contact_form';
  case Docs = 'docs';
  case Export = 'export';
  case Forum = 'forum';  
  case Home = 'home';  
  case Images = 'images';  
  case Login = 'login';
  case Logout = 'logout';
  case Logs = 'logs';
  case Messages = 'messages';
  case News = 'news';
  case Notifications = 'notifications';
  case PasswordReset = 'password_reset';
  case RegisterForm = 'register_form';  
  case SupportForm = 'support_form';  
  case Transactions = 'transactions';
  case Users = 'users';

  public function get_parent(): RouteModuleInterface|null
  {
    return null;
  }

  public function get_config_id():string|null
  {
    return match ($this) {
      self::Chat => 'chat.enabled',
      self::Cms => null, 
      self::Config => null, 
      self::ContactForm => 'contact_form.enabled',
      self::Docs => 'docs.enabled',
      self::Export => null,
      self::Forum => 'forum.enabled',
      self::Home => null,
      self::Images => null,
      self::Login => null,
      self::Logout => null,
      self::Logs => null,
      self::Messages => 'messages.enabled',
      self::News => 'news.enabled',
      self::Notifications => null,
      self::PasswordReset => null,
      self::RegisterForm => 'register_form.enabled',
      self::SupportForm => 'support_form.enabled',
      self::Transactions => 'transactions.enabled',
      self::Users => 'users.enabled',
    };
  }
}
