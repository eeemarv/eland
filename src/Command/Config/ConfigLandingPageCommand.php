<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Cnst\ConfigCnst;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;

class ConfigLandingPageCommand implements CommandInterface
{
  #[Choice(choices: ConfigCnst::LANDING_PAGE_OPTIONS)]
  #[ConfigMap(type: 'str', key: 'system.default_landing_page')]
  public mixed $landing_page;
}
