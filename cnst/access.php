<?php

namespace cnst;

class access
{
    const ROUTE = [
        'login'         => 'anonymous',
        'contact'       => 'anonymous',
        'register'      => 'anonymous',
        'index'         => 'anonymous',
        'messages'      => 'guest',
        'users'         => 'guest',
        'news'          => 'guest',
        'docs'          => 'guest',
        'forum'         => 'guest',
        'support'       => 'user',
        'status'        => 'admin',
        'categories'    => 'admin',
        'contact_types' => 'admin',
        'contacts'      => 'guest',
        'config'        => 'admin',
        'autominlimit'  => 'admin',
        'logs'          => 'admin',
        'apikeys'       => 'admin',
    ];

    const LABEL_ARY = [
        'admin'		=> 'Admin',
        'user'		=> 'User',
        'interlets'	=> 'InterSysteem',
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

    const TRANSFORM = [
        'to_db'     => [
            'admin'		=> 0,
            'user'		=> 1,
            'guest'		=> 2,
            'anonymous'	=> 3,
        ],
        'from_db'   => [
            0   => 'admin',
            1   => 'user',
            2   => 'guest',
            3   => 'anonymous',
        ],
    ];
}