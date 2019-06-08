<?php

namespace cnst;

class access
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

    const FROM_FLAG_PUBLIC = [
        0   => 'admin',
        1   => 'user',
        2   => 'guest',
    ];

    const TO_FLAG_PUBLIC = [
        'admin' => 0,
        'user'  => 1,
        'guest' => 2,
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
