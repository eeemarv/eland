<?php declare(strict_types=1);

namespace App\Cnst;

class ProcessCnst
{
    const INTERVAL = [
        'cleanup'    => [
            'wait'      => 900,
            'monitor'   => 3600,
            'log'       => 100,
        ],
        'cleanup_cache'    => [
            'wait'      => 7200,
            'monitor'   => 14400,
            'log'       => 1,
        ],
        'cleanup_images'    => [
            'wait'      => 900,
            'monitor'   => 3600,
            'log'       => 100,
        ],
        'cleanup_logs'    => [
            'wait'      => 14400,
            'monitor'   => 28800,
            'log'       => 1,
        ],
        'geocode'    => [
            'wait'      => 120,
            'monitor'   => 900,
            'log'       => 5000,
        ],
        'log'    => [
            'wait'      => 5,
            'monitor'   => 300,
            'log'       => 10000,
        ],
        'mail'    => [
            'wait'      => 5,
            'monitor'   => 300,
            'log'       => 10000,
        ],
        'worker'    => [
            'wait'      => 120,
            'monitor'   => 900,
            'log'       => 500,
        ],
    ];
}
