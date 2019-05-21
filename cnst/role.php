<?php

namespace cnst;

class role
{
    const LABEL___ARY = [
        'admin'		=> 'Admin',
        'user'		=> 'User',
        //'guest'		=> 'Guest', //is not a primary role, but a speudo role
        'interlets'	=> 'InterSysteem',
    ];

    const LABEL_ARY = [
        'admin' => 'Admin',
        'user'  => 'Gebruiker',
        'guest' => 'Gast',
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
