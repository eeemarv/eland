<?php declare(strict_types=1);

namespace App\Validator\BulkSelect;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class BulkSelectNotEmpty extends Constraint
{
  public function __construct(
    public readonly string $message = 'bulk_select.not_empty.generic',
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