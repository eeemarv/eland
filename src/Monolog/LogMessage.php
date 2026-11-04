<?php declare(strict_types=1);

namespace App\Monolog;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'log')]
class LogMessage
{
  public function __construct(
    public array $record,
  )
  {
  }
}
