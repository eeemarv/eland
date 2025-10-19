<?php declare(strict_types=1);

namespace App\Validator\PlainText;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PlainTextLength extends Constraint
{
  public string $message_min = 'plain_text_length.min';
  public string $message_max = 'plain_text_length.max';

  public function __construct(
    public readonly int $min = 0,
    public readonly int $max = 30000,
    string|null $message_min = null,
    string|null $message_max = null,
    array|null $groups = null,
    mixed $payload = null,
  )
  {
    parent::__construct([], $groups, $payload);
    if (isset($message_min)){
      $this->message_min = $message_min;
    }
    if (isset($message_max)){
      $this->message_min = $message_max;
    }
  }
}
