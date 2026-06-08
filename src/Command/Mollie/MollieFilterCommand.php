<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class MollieFilterCommand implements CommandInterface
{
  #[Type(type: 'string')]
  public mixed $q;

  #[Type(type: 'int')]
  public mixed $user;

  #[Choice(choices: ['open', 'paid', 'canceled'], multiple: true)]
  public mixed $status;

  #[Type(type: 'string')]
  public mixed $from_date;

  #[Type(type: 'string')]
  public mixed $to_date;
}
