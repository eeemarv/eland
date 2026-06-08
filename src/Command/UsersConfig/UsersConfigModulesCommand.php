<?php declare(strict_types=1);

namespace App\Command\UsersConfig;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigModulesCommand implements CommandInterface
{
  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.fields.full_name.enabled')]
  public mixed $full_name_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.fields.postcode.enabled')]
  public mixed $postcode_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.fields.birthdate.enabled')]
  public mixed $birthdate_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.fields.hobbies.enabled')]
  public mixed $hobbies_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.fields.comments.enabled')]
  public mixed $comments_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.fields.admin_comments.enabled')]
  public mixed $admin_comments_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.new.enabled')]
  public mixed $new_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.leaving.enabled')]
  public mixed $leaving_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'intersystem.enabled')]
  public mixed $intersystem_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'periodic_mail.enabled')]
  public mixed $periodic_mail_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'mollie.enabled')]
  public mixed $mollie_enabled;
}
