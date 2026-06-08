<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class MessagesModulesCommand implements CommandInterface
{
  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'messages.fields.service_stuff.enabled')]
  public mixed $service_stuff_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'messages.fields.category.enabled')]
  public mixed $category_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'messages.fields.expires_at.enabled')]
  public mixed $expires_at_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'messages.fields.units.enabled')]
  public mixed $units_enabled;
}
