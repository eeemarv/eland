<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Command\CommandInterface;
use App\Validator\StartsWith\StartsWith;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MollieConfigLiveApiKeyCommand implements CommandInterface
{
  #[NotBlank()]
  #[StartsWith(prefix: 'live_')]
  #[Length(min: 20, max: 200)]
  public mixed $live_api_key;
}
