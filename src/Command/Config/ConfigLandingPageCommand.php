<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Url;

class ConfigLandingPageCommand implements CommandInterface
{
  #[Url()]
  #[ConfigMap(type: 'str', key: 'system.default_landing_page')]
  public mixed $landing_page;
}
