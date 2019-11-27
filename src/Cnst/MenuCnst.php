<?php declare(strict_types=1);

namespace App\Cnst;

class MenuCnst
{
    const SIDEBAR = [
        'messages'		=> [
            'access'    => 'guest',
            'fa'        => 'newspaper-o',
            'label'     => 'Vraag & Aanbod',
            'var_route' => 'r_messages',
        ],
        'users'			=> [
            'access'    => 'guest',
            'fa'        => 'users',
            'label'       => 'Leden',
            'label_admin' => 'Gebruikers',
            'var_route' => 'r_users',
        ],
        'transactions'	=> [
            'access'        => 'guest',
            'fa'            => 'exchange',
            'label'         => 'Transacties',
        ],
        'news'			=> [
            'access'        => 'guest',
            'fa'            => 'calendar-o',
            'label'         => 'Nieuws',
            'var_route'     => 'r_news',
        ],
        'docs' 			=> [
            'access'        => 'guest',
            'fa'            => 'files-o',
            'label'         => 'Documenten',
        ],
        'forum'         => [
            'access'        => 'guest',
            'fa'            => 'comments-o',
            'label'         => 'Forum',
            'config_en'     => 'forum_en',
        ],
        'support'       => [
            'access'        => 'user',
            'fa'            => 'ambulance',
            'label'         => 'Probleem melden',
        ],
        'login'     => [
            'access'    => 'anonymous',
            'fa'        => 'sign-in',
            'label'     => 'Login',
        ],
        'contact'   => [
            'access'    => 'anonymous',
            'fa'        => 'comment-o',
            'label'     => 'Contact',
            'config_en' => 'contact_form_en',
        ],
        'register'  => [
            'access'    => 'anonymous',
            'fa'        => 'check-square-o',
            'label'     => 'Inschrijven',
            'config_en' => 'registration_en',
        ],
    ];

    const NAV_USER = [
        'users_show'    => [
            'fa'        => 'user',
            'label'     => 'Mijn gegevens',
            'params'    => [],
        ],
        'messages' => [
            'fa'        => 'newspaper-o',
            'label'     => 'Mijn vraag en aanbod',
            'params'    => ['f' => []],
        ],
        'transactions' => [
            'fa'        => 'exchange',
            'label'     => 'Mijn transacties',
            'params'    => ['f' => []],
            'route'     => 'transactions',
        ],
        'divider_1'     => [
            'divider'   => true,
        ],
    ];

    const NAV_LOGOUT = [
        'logout'        => [
            'fa'        => 'sign-out',
            'label'     => 'Uitloggen',
            'route'     => 'logout',
        ],
    ];

    const NAV_ADMIN = [
        'admin_mode'    => [
            'fa'            => 'cog',
            'label'         => 'Admin modus',
            'params'        => [
                'role_short'    => 'a',
            ],
        ],
        'user_mode'    => [
            'fa'            => 'user',
            'label'         => 'Leden modus',
            'params'        => [
                'role_short'    => 'u',
            ],
        ],
        'guest_mode'    => [
            'fa'            => 'share-alt',
            'label'         => 'Gast modus',
            'params'        => [
                'role_short'    => 'g',
            ],
        ],
        'divider_1' => [
            'divider'   => true,
        ],
        'config'			=> [
            'fa'        => 'gears',
            'label'     => 'Instellingen',
            'route'     => 'config',
            'params'    => [
                'role_short'    => 'a',
            ],
        ],
        'export'			=> [
            'fa'        => 'download',
            'label'     => 'Export',
            'route'     => 'export',
            'params'    => [
                'role_short'    => 'a',
            ],
        ],
        'logs'				=> [
            'fa'        => 'history',
            'label'     => 'Logs',
            'route'     => 'logs',
            'params'    => [
                'role_short'    => 'a',
            ],
        ],
    ];

    const LOCAL_ADMIN_MAIN = [
        'categories'        => 'messages',
        'status'            => 'users',
        'contacts'          => 'users',
        'contact_types'     => 'users',
        'intersystems'      => 'users',
        'autominlimit'      => 'transactions',
        'mass_transaction'  => 'transactions',
    ];

    const LOCAL_ADMIN = [
        'messages'  => [
            'categories'	 	=> [
                'fa'        => 'clone',
                'label'     => 'Categorieën',
                'route'     => 'categories',
            ],
        ],
        'users' => [
            'status'			=> [
                'fa'        => 'exclamation-triangle',
                'label'     => 'Status',
                'route'     => 'status',
            ],
            'contacts'			=> [
                'fa'        => 'map-marker',
                'label'     => 'Contacten',
                'route'     => 'contacts',
            ],
            'contact_types'		=> [
                'fa'        => 'circle-o-notch',
                'label'     => 'Contact Types',
                'route'     => 'contact_types',
            ],
            'intersystems'		=> [
                'fa'        => 'share-alt',
                'label'     => 'InterSysteem',
                'route'     => 'intersystems',
            ],
        ],
        'transactions' => [
            'autominlimit'		=> [
                'fa'        => 'arrows-v',
                'label'     => 'Auto Min Limiet',
                'route'     => 'autominlimit',
            ],
            'mass_transaction'	=> [
                'fa'        => 'exchange',
                'label'     => 'Massa-Transactie',
                'route'     => 'mass_transaction',
            ],
        ],
    ];
}
