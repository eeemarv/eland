<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class ConfigModulesCommand implements CommandInterface
{
  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'messages.enabled')]
  public mixed $messages_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'transactions.enabled')]
  public mixed $transactions_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'news.enabled')]
  public mixed $news_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'docs.enabled')]
  public mixed $docs_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'forum.enabled')]
  public mixed $forum_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'support_form.enabled')]
  public mixed $support_form_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'home.menu.enabled')]
  public mixed $home_menu_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'contact_form.enabled')]
  public mixed $contact_form_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'register_form.enabled')]
  public mixed $register_form_enabled;
}
