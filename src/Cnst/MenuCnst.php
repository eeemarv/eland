<?php declare(strict_types=1);

namespace App\Cnst;

class MenuCnst
{
    const SIDEBAR = [
        'messages'		=> [
            'access'    => 'guest',
            'fa'        => 'newspaper-o',
            'label'     => 'Vraag & Aanbod',
            'config_en' => 'messages.enabled',
        ],
        'users'			=> [
            'access'    => 'guest',
            'fa'        => 'users',
            'label'       => 'Leden',
            'label_admin' => 'Gebruikers',
        ],
        'transactions'	=> [
            'access'        => 'guest',
            'fa'            => 'exchange',
            'label'         => 'Transacties',
            'config_en'     => 'transactions.enabled',
        ],
        'news'			=> [
            'access'        => 'guest',
            'fa'            => 'calendar-o',
            'label'         => 'Nieuws',
            'config_en'     => 'news.enabled',
        ],
        'docs' 			=> [
            'access'        => 'guest',
            'fa'            => 'files-o',
            'label'         => 'Documenten',
            'config_en'     => 'docs.enabled',
        ],
        'forum'         => [
            'access'        => 'guest',
            'fa'            => 'comments-o',
            'label'         => 'Forum',
            'config_en'     => 'forum.enabled',
        ],
        'support'       => [
            'access'        => 'user',
            'fa'            => 'ambulance',
            'label'         => 'Probleem melden',
            'config_en'     => 'support_form.enabled',
        ],
        'home'      => [
            'access'    => 'anonymous',
            'fa'        => 'home',
            'label'     => 'Home',
            'config_en' => 'home.menu.enabled',
        ],
        'login'     => [
            'access'    => 'anonymous',
            'fa'        => 'sign-in',
            'label'     => 'Login',
        ],
        'contact_form'   => [
            'access'    => 'anonymous',
            'fa'        => 'comment-o',
            'label'     => 'Contact',
            'config_en' => 'contact_form.enabled',
        ],
        'register_form'  => [
            'access'    => 'anonymous',
            'fa'        => 'check-square-o',
            'label'     => 'Inschrijven',
            'config_en' => 'register_form.enabled',
        ],
    ];

    const NAV_USER = [
        'users_show'    => [
            'fa'            => 'user',
            'label'         => 'Mijn gegevens',
            'params_id'     => true,
        ],
        'messages' => [
            'fa'            => 'newspaper-o',
            'label'         => 'Mijn vraag en aanbod',
            'params_filter_uid'    => true,
            'config_en'     => 'messages.enabled',
        ],
        'transactions' => [
            'fa'        => 'exchange',
            'label'     => 'Mijn transacties',
            'params_filter_uid'     => true,
            'config_en' => 'transactions.enabled',
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
            'same_route'    => true,
            'params'        => [
                'role_short'    => 'a',
            ],
        ],
        'user_mode'    => [
            'fa'            => 'user',
            'label'         => 'Leden modus',
            'fallback_route'    => true,
            'params'        => [
                'role_short'    => 'u',
            ],
        ],
        'guest_mode'    => [
            'fa'            => 'share-alt',
            'label'         => 'Gast modus',
            'fallback_route'    => true,
            'params'        => [
                'role_short'    => 'g',
            ],
        ],
        'divider_1' => [
            'divider'   => true,
        ],

        'edit_mode' => [
            'fa'            => 'pencil',
            'label'         => 'CMS Edit modus',
            'same_route'    => true,
            'params'        => [
                'edit'      => '1',
            ],
        ],
        'divider_2' => [
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
        'messages_modules'  => 'messages',
        'messages_cleanup'  => 'messages',
        'categories'        => 'messages',
        'status'            => 'users',
        'contacts'          => 'users',
        'contact_types'     => 'users',
        'intersystems'      => 'users',
        'mollie_payments'   => 'users',
        'autominlimit'      => 'transactions',
        'mass_transaction'  => 'transactions',
    ];

    const LOCAL_ADMIN = [
        'messages'  => [
            'messages_modules'   => [
                'fa'        => 'cog',
                'label'     => 'Submodules en velden',
                'route'     => 'messages_modules',
            ],
            'categories'	 	=> [
                'fa'        => 'clone',
                'label'     => 'Categorieën',
                'route'     => 'categories',
                'config_en' => 'messages.fields.category.enabled',
            ],
            'messages_cleanup'   => [
                'fa'        => 'trash-o',
                'label'     => 'Geldigheid en opruiming',
                'route'     => 'messages_cleanup',
                'config_en' => 'messages.fields.expires_at.enabled',
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
            'mollie_payments' => [
                'fa'        => 'eur',
                'label'     => 'Mollie betaalverzoeken',
                'route'     => 'mollie_payments',
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
