<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MollieConfigWebhookKeyCommand implements CommandInterface
{
  #[NotBlank()]
  #[Length(min: 20, max: 200)]
  public mixed $webhook_key;
}
