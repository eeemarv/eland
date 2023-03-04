<?php declare(strict_types=1);

namespace App\Cnst;

class AccessCnst
{
    const LABEL = [
        'admin'		=> [
            'lbl'   => 'admin',
            'class' => 'info',
            'title' => 'Alleen de eigenaar en admins kunnen dit zien',
        ],
        'user'		=> [
            'lbl'   => 'leden',
            'class' => 'default',
            'title' => 'Alle leden van dit systeem kunnen dit zien',
        ],
        'guest'	    => [
            'lbl'   => 'interSysteem',
            'class' => 'warning',
            'title' => 'Alle leden van dit systeem en alle leden van verbonden interSystemen kunnen dit zien',
        ],
    ];

    const ARY = [
        'admin' => 'admin',
        'user'  => 'user',
        'guest' => 'guest',
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
