<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Command\CommandInterface;
use App\Validator\StartsWith\StartsWith;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MollieConfigTestApiKeyCommand implements CommandInterface
{
  #[NotBlank()]
  #[StartsWith(prefix: 'test_')]
  #[Length(min: 20, max: 200)]
  public mixed $test_api_key;
}
