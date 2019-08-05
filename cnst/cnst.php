<?php declare(strict_types=1);

namespace cnst;

class cnst
{
    const ROLE_ARY = [
        'admin'		=> 'Admin',
        'user'		=> 'User',
        //'guest'		=> 'Guest', //is not a primary role, but a speudo role
        'interlets'	=> 'InterSysteem',
    ];

    const ROLE_SHORT = [
        'admin'     => 'a',
        'user'      => 'u',
        'guest'     => 'g',
    ];

    const ROLE_LONG = [
        'a' => 'admin',
        'u' => 'user',
        'g' => 'guest',
    ];

    const ACCESS = [
        'admin' => [
            'admin'     => true,
            'user'      => true,
            'guest'     => true,
            'anonymous' => true,
        ],
        'user'  => [
            'user'      => true,
            'guest'     => true,
            'anonymous' => true,
        ],
        'guest' => [
            'guest'     => true,
            'anonymous' => true,
        ],
        'anonymous' => [
            'anonymous' => true,
        ],
    ];

    const STATUS_ARY = [
        0	=> 'Gedesactiveerd',
        1	=> 'Actief',
        2	=> 'Uitstapper',
        //3	=> 'Instapper',    // not used in selector
        //4 => 'Secretariaat, // not used
        5	=> 'Info-pakket',
        6	=> 'Info-moment',
        7	=> 'Extern',
    ];

    const ACCESS_ARY = [
        'admin'		=> 0,
        'user'		=> 1,
        'guest'		=> 2,
        'anonymous'	=> 3,
    ];

    const INTERSYSTEM_LANDING_PAGES = [
        'messages'		=> true,
        'users'			=> true,
        'transactions'	=> true,
        'news'			=> true,
        'docs'			=> true,
    ];

    const ELAS_CACHE_KEY = [
        'last_fetch'        => 'elas_interlets_last_fetch',
        'apikey_fails'      => 'elas_interlets_apikey_fails',
        'domains'           => 'elas_interlets_domains',
    ];

    const PROCESS_INTERVAL = [
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
        'fetch_elas_intersystem'    => [
            'wait'      => 450,
            'monitor'   => 3600,
            'log'       => 100,
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
