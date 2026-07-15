<?php declare(strict_types=1);

namespace App\Validator\StartsWith;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class StartsWith extends Constraint
{
  public function __construct(
    public readonly string $prefix,
    public readonly string  $message = 'starts_with',
    array|null $groups = null,
    mixed $payload = null,
  )
  {
    parent::__construct(
      options: [],
      groups: $groups,
      payload: $payload,
    );
  }
}
