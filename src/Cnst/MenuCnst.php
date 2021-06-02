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
        'users_show_self'   => [
            'fa'        => 'user',
            'label'     => 'Mijn gegevens',
            'route'     => 'users_show_self',
        ],
        'messages_self' => [
            'fa'            => 'newspaper-o',
            'label'         => 'Mijn vraag en aanbod',
            'config_en'     => 'messages.enabled',
            'var_route'     => 'messages_self',
        ],
        'transactions_self' => [
            'fa'        => 'exchange',
            'label'     => 'Mijn transacties',
            'config_en' => 'transactions.enabled',
            'route'     => 'transactions_self',
        ],
        'divider_1'     => [
            'divider'   => true,
        ],
        'logout'        => [
            'fa'        => 'sign-out',
            'label'     => 'Uitloggen',
            'route'     => 'logout',
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
                'edit'      => [
                    'en'        => '1',
                    'route'     => '1',
                    'inline'    => '1',
                ],
            ],
        ],
        'divider_2' => [
            'divider'   => true,
        ],

        'config_name'       => [
            'fa'        => 'gears',
            'label'     => 'Instellingen',
            'route'     => 'config_name',
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
        'messages_modules'      => 'messages',
        'messages_cleanup'      => 'messages',
        'categories'            => 'messages',
        'users_modules'         => 'users',
        'status'                => 'users',
        'contacts'              => 'users',
        'contact_types'         => 'users',
        'users_periodic_mail'   => 'users',
        'intersystems'          => 'users',
        'mollie_payments'       => 'users',
        'transactions_currency'         => 'transactions',
        'transactions_system_limits'    => 'transactions',
        'transactions_leaving_eq'       => 'transactions',
        'transactions_modules'          => 'transactions',
        'transactions_autominlimit'     => 'transactions',
        'transactions_mass'             => 'transactions',
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
            'users_modules' => [
                'fa'        => 'cog',
                'label'     => 'Submodules en velden',
                'route'     => 'users_modules',
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
            'users_periodic_mail'   => [
                'fa'        => 'envelope-o',
                'label'     => 'Periodiek Overzicht',
                'route'     => 'users_periodic_mail',
                'config_en' => 'periodic_mail.enabled',
            ],
            'intersystems'		=> [
                'fa'        => 'share-alt',
                'label'     => 'InterSysteem',
                'route'     => 'intersystems',
                'config_en' => 'intersystem.enabled',
            ],
            'mollie_payments' => [
                'fa'        => 'eur',
                'label'     => 'Mollie betaalverzoeken',
                'route'     => 'mollie_payments',
                'config_en' => 'mollie.enabled',
            ],
        ],
        'transactions' => [
            'transactions_modules'  => [
                'fa'    => 'cog',
                'label' => 'Submodules en velden',
                'route' => 'transactions_modules',
            ],
            'transactions_currency' => [
                'fa'    => 'money',
                'label' => 'Munteenheid',
                'route' => 'transactions_currency',
            ],
            'transactions_system_limits'    => [
                'fa'        => 'arrows-v',
                'label'     => 'Systeemslimieten',
                'route'     => 'transactions_system_limits',
                'config_en' => 'accounts.limits.enabled',
            ],
            'transactions_autominlimit'		=> [
                'fa'        => 'chevron-down',
                'label'     => 'Auto Min Limiet',
                'route'     => 'transactions_autominlimit',
                'config_en' => 'accounts.limits.auto_min.enabled',
            ],
            'transactions_leaving_eq'		=> [
                'fa'        => 'balance-scale',
                'label'     => 'Uitstappers saldo',
                'route'     => 'transactions_leaving_eq',
                'config_en' => 'users.leaving.enabled',
            ],
            'transactions_mass'	=> [
                'fa'        => 'exchange',
                'label'     => 'Massa-Transactie',
                'route'     => 'transactions_mass',
                'config_en' => 'transactions.mass.enabled',
            ],
        ],
    ];
}
