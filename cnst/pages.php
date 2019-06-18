<?php

namespace cnst;

class pages
{
    const DEFAULT_VIEW = [
        'users'		=> 'users_list',
        'messages'	=> 'messages_extended',
        'news'		=> 'news_extended',
    ];

    const INTERSYSTEM_LANDING = [
        'messages'		=> true,
        'users'			=> true,
        'transactions'	=> true,
        'news'			=> true,
        'docs'			=> true,
    ];

    const MENU = [
        'messages'  => [
            'fa'        => '',
            'routes'    => [
                'messages_list' => [
                    'fa'    => '',
                ],
                'messages_extended' => [
                    'fa'    => '',
                ],
            ],
        ],
        'users'     => [
            'fa'    => 'users',
            'routes'    => [
                'users_list'    => [
                    'fa'    => '',
                ],
                'users_tiles'   => [
                    'fa'    => '',
                ],
                'users_map'     => [
                    'fa'    => 'map-marker',
                ],
            ],
        ],
        'transactions'  => [
            'fa'    => 'exchange',
        ],
        'news'  => [
            'fa'    => 'calendar-o',
            'routes'    => [
                'news_list' => [
                    'fa'    => '',
                ],
                'news_extended' => [
                    'fa'    => '',
                ],
            ],
        ],
    ];
}
