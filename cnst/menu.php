<?php declare(strict_types=1);

namespace cnst;

class menu
{
    const SIDEBAR = [
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
        'status'			=> [
            'fa'        => 'exclamation-triangle',
            'label'     => 'Status',
            'route'     => 'status',
        ],
        'categories'	 	=> [
            'fa'        => 'clone',
            'label'     => 'Categorieën',
            'route'     => 'categories',
        ],
        'contact_types'		=> [
            'fa'        => 'circle-o-notch',
            'label'     => 'Contact Types',
            'route'     => 'contact_types',
        ],
        'contacts'			=> [
            'fa'        => 'map-marker',
            'label'     => 'Contacten',
            'route'     => 'contacts',
        ],
        'config'			=> [
            'fa'        => 'gears',
            'label'     => 'Instellingen',
            'route'     => 'config',
        ],
        'intersystems'		=> [
            'fa'        => 'share-alt',
            'label'     => 'InterSysteem',
            'route'     => 'intersystems',
        ],
        'apikeys'			=> [
            'fa'        => 'key',
            'label'     => 'Apikeys',
            'route'     => 'apikeys',
        ],
        'export'			=> [
            'fa'        => 'download',
            'label'     => 'Export',
            'route'     => 'export',
        ],
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
        'logs'				=> [
            'fa'        => 'history',
            'label'     => 'Logs',
            'route'     => 'logs',
        ],
        'divider_1'     => [
            'divider'   => true,
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
    ];
}
