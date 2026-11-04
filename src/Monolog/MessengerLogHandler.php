<?php declare(strict_types=1);

namespace App\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Messenger\MessageBusInterface;

class MessengerLogHandler extends AbstractProcessingHandler
{
  public function __construct(
    private MessageBusInterface $bus, 
    int|string|Level $level = Level::Debug, 
    bool $bubble = true
  )
  {
    parent::__construct(
      level: $level, 
      bubble: $bubble,
    );
  }

  protected function write(
    LogRecord $record,
  ): void 
  {
    $this->bus->dispatch(
      message: new LogMessage($record->toArray()),
    );
  }
}