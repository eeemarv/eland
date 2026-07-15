<?php declare(strict_types=1);

namespace App\Validator\PlainText;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PlainTextLength extends Constraint
{
  public function __construct(
    public readonly int $min = 0,
    public readonly int $max = 30000,
    public readonly string $message_min = 'plain_text_length.min',
    public readonly string $message_max = 'plain_text_length.max',
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
