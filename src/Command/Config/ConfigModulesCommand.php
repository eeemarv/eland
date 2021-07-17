<?php declare(strict_types=1);

namespace App\Command\Config;

use Symfony\Component\Validator\Constraints\Type;

class ConfigModulesCommand
{
    #[Type('bool')]
    public $messages_enabled;

    #[Type('bool')]
    public $transactions_enabled;

    #[Type('bool')]
    public $news_enabled;

    #[Type('bool')]
    public $docs_enabled;

    #[Type('bool')]
    public $forum_enabled;

    #[Type('bool')]
    public $support_form_enabled;

    #[Type('bool')]
    public $home_menu_enabled;

    #[Type('bool')]
    public $contact_form_enabled;

    #[Type('bool')]
    public $register_form_enabled;
}
