<?php declare(strict_types=1);

namespace App\Cnst;

class MenuCnst
{
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
        'users_username'        => 'users',
        'users_full_name'       => 'users',
        'contacts'              => 'users',
        'contact_types'         => 'users',
        'users_periodic_mail'   => 'users',
        'users_config_new'      => 'users',
        'users_config_leaving'  => 'users',
        'intersystems'          => 'users',
        'mollie_payments'       => 'users',
        'transactions_currency'         => 'transactions',
        'transactions_system_limits'    => 'transactions',
        'transactions_modules'          => 'transactions',
        'transactions_autominlimit'     => 'transactions',
        'transactions_mass'             => 'transactions',
        'news_sort'                     => 'news',
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
            'users_username'   => [
                'fa'        => 'user',
                'label'     => 'Gebruikersnaam',
                'route'     => 'users_username',
            ],
            'users_full_name'   => [
                'fa'        => 'user',
                'label'     => 'Volledige naam',
                'route'     => 'users_full_name',
                'config_en' => 'users.fields.full_name.enabled',
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
            'users_config_new'   => [
                'fa'        => 'user-plus',
                'label'     => 'Instappers',
                'route'     => 'users_config_new',
                'config_en' => 'users.new.enabled',
            ],
            'users_config_leaving'   => [
                'fa'        => 'user-times',
                'label'     => 'Uitstappers',
                'route'     => 'users_config_leaving',
                'config_en' => 'users.leaving.enabled',
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
            'transactions_mass'	=> [
                'fa'        => 'exchange',
                'label'     => 'Massa-Transactie',
                'route'     => 'transactions_mass',
                'config_en' => 'transactions.mass.enabled',
            ],
        ],
        'news'  => [
            'news_sort'   => [
                'fa'        => 'sort',
                'label'     => 'Sortering',
                'route'     => 'news_sort',
            ],
        ],
    ];
}
