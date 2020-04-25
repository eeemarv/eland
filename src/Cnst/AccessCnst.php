<?php declare(strict_types=1);

namespace App\Cnst;

class AccessCnst
{
    const LABEL = [
        'admin'		=> [
            'lbl'   => 'admin',
            'class' => 'info',
        ],
        'user'		=> [
            'lbl'   => 'leden',
            'class' => 'default'
        ],
        'guest'	    => [
            'lbl'   => 'interSysteem',
            'class' => 'warning',
        ],
    ];

    const ARY = [
        'admin' => 'admin',
        'user'  => 'user',
        'guest' => 'guest',
    ];

    const FROM_XDB = [
        'admin'     => 'admin',
        'users'     => 'user',
        'interlets' => 'guest',
    ];

    const TO_XDB = [
        'admin' => 'admin',
        'user'  => 'users',
        'guest' => 'interlets',
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
}
