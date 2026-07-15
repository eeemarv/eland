<?php declare(strict_types=1);

namespace App\Validator\Account;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class AccountIdInMulti extends Constraint
{
  public function __construct(
    public readonly string $message = 'account_id.does_not_exist',
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