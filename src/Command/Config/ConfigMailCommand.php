<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class ConfigMailCommand implements CommandInterface
{
  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'mail.enabled')]
  public mixed $enabled;

  #[Sequentially(constraints: [
      new NotBlank(),
      new Length(max: 20),
  ])]
  #[ConfigMap(type: 'str', key: 'mail.tag')]
  public mixed $tag;

  #[Sequentially(constraints: [
    new NotNull(),
    new NotBlank(),
  ])]
  #[ConfigMap(type: 'str', key: 'mail.style.background')]
  public mixed $background;
}
