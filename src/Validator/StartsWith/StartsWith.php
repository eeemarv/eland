<?php declare(strict_types=1);

namespace App\Validator\StartsWith;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class StartsWith extends Constraint
{
  public string $message = 'starts_with';

  public function __construct(
    public readonly string $prefix,
    string|null $message = null,
    array|null $groups = null,
    mixed $payload = null,
  )
  {
    parent::__construct([], $groups, $payload);
    if (isset($message)){
      $this->message = $message;
    }
  }
}
