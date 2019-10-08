<?php declare(strict_types=1);

namespace App\Cnst;

class RoleCnst
{
    const LABEL_ARY = [
        'admin'         => 'Admin',
        'user'          => 'Gebruiker',
        'interlets'     => 'InterSysteem',
    ];

    const SHORT = [
        'admin'     => 'a',
        'user'      => 'u',
        'guest'     => 'g',
    ];

    const LONG = [
        'a' => 'admin',
        'u' => 'user',
        'g' => 'guest',
    ];
}
