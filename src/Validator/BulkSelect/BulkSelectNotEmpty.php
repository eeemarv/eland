<?php declare(strict_types=1);

namespace App\Validator\BulkSelect;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class BulkSelectNotEmpty extends Constraint
{
  public string $message = 'bulk_select.not_empty.generic';

  public function __construct(
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