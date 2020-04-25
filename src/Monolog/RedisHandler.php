<?php declare(strict_types=1);

namespace App\Monolog;

use Monolog\Formatter\JsonFormatter;
use Predis\Client as Predis;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class RedisHandler extends AbstractProcessingHandler
{
    const KEY = 'monolog_logs';

    protected Predis $predis;

    public function __construct(
        Predis $predis,
        $level = Logger::DEBUG,
        $bubble = true
    )
    {
        parent::__construct($level, $bubble);

        $this->predis = $predis;
    }

    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }

    protected function write(array $record): void
    {
        if (!isset($record['context']['schema']))
        {
            error_log('APP LOG without schema: ' . $record['message']);
            return;
        }

        $this->predis->rpush(self::KEY, $record['formatted']);
    }
}
