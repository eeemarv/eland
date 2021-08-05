<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class ConfigModulesCommand implements CommandInterface
{
    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.enabled')]
    public $messages_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'transactions.enabled')]
    public $transactions_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'news.enabled')]
    public $news_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'docs.enabled')]
    public $docs_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'forum.enabled')]
    public $forum_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'support_form.enabled')]
    public $support_form_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'home.menu.enabled')]
    public $home_menu_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'contact_form.enabled')]
    public $contact_form_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'register_form.enabled')]
    public $register_form_enabled;
}
