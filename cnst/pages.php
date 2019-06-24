<?php

namespace cnst;

class pages
{
    const INTERSYSTEM_LANDING = [
        'messages'		=> true,
        'users'			=> true,
        'transactions'	=> true,
        'news'			=> true,
        'docs'			=> true,
    ];

    const DEFAULT_VIEW = [
        'users'		=> 'list',
        'messages'	=> 'extended',
        'news'		=> 'extended',
    ];

    const ROUTE_TO_VIEW = [
        'users_list'        => ['users', 'list'],
        'users_list_admin'  => ['users', 'list'],
        'users_tiles'       => ['users', 'tiles'],
        'users_tiles_admin' => ['users', 'tiles'],
        'users_map'         => ['users', 'map'],
        'messages_list'     => ['messages', 'list'],
        'messages_extended' => ['messages', 'extended'],
        'news_list'         => ['news', 'list'],
        'news_extended'     => ['news', 'extended'],
    ];

    const SIDE_MENU = [
        'login'     => [
            'access'    => 'anonymous',
            'fa'        => 'sign-in',
            'lbl'       => 'Login',
        ],
        'contact'   => [
            'access'    => 'anonymous',
            'fa'        => 'comment-o',
            'lbl'       => 'Contact',
            'config_en' => 'contact_form_en',
        ],
        'register'  => [
            'access'    => 'anonymous',
            'fa'        => 'check-square-o',
            'lbl'       => 'Inschrijven',
            'config_en' => 'registration_en',
        ],
        'messages'		=> [
            'access'    => 'guest',
            'fa'        => 'newspaper-o',
            'lbl'       => 'Vraag & Aanbod',
            'var_route' => 'r_messages',
        ],
        'users'			=> [
            'access'    => 'guest',
            'fa'        => 'users',
            'lbl'       => 'Leden',
            'lbl_admin' => 'Gebruikers',
            'var_route' => 'r_users',
        ],
        'transactions'	=> [
            'access'        => 'guest',
            'fa'            => 'exchange',
            'lbl'           => 'Transacties',
        ],
        'news'			=> [
            'access'        => 'guest',
            'fa'            => 'calendar-o',
            'lbl'           => 'Nieuws',
            'var_route'     => 'r_news',
        ],
        'docs' 			=> [
            'access'        => 'guest',
            'fa'            => 'files-o',
            'lbl'           => 'Documenten',
        ],
        'forum'         => [
            'access'        => 'guest',
            'fa'            => 'comments-o',
            'lbl'           => 'Forum',
            'config_en'     => 'forum_en',
        ],
        'support'       => [
            'access'        => 'user',
            'fa'            => 'ambulance',
            'lbl'           => 'Probleem melden',
        ],
    ];

    const PPMENU = [
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
