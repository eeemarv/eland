<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class UsersModulesCommand implements CommandInterface
{
    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.tags.enabled')]
    public $tags_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.full_name.enabled')]
    public $full_name_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.postcode.enabled')]
    public $postcode_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.birthday.enabled')]
    public $birthday_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.hobbies.enabled')]
    public $hobbies_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.comments.enabled')]
    public $comments_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.admin_comments.enabled')]
    public $admin_comments_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.new.enabled')]
    public $new_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.leaving.enabled')]
    public $leaving_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'intersystem.enabled')]
    public $intersystem_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'periodic_mail.enabled')]
    public $periodic_mail_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'mollie.enabled')]
    public $mollie_enabled;
}
