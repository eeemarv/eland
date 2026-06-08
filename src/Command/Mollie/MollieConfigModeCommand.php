<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class MollieConfigModeCommand implements CommandInterface
{
  #[NotBlank()]
  #[Choice(choices: ['none', 'test', 'live'])]
  #[ConfigMap(type: 'str', key: 'mollie.mode')]
  public mixed $mollie_mode;
}
