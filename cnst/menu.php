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

    const NAV_ADMIN = [
        'status'			=> [
            'fa'        => 'exclamation-triangle',
            'label'     => 'Status',
        ],
        'categories'	 	=> [
            'fa'        => 'clone',
            'label'     => 'Categorieën',
        ],
        'contact_types'		=> [
            'fa'        => 'circle-o-notch',
            'label'     => 'Contact Types',
        ],
        'contacts'			=> [
            'fa'        => 'map-marker',
            'label'     => 'Contacten',
        ],
        'config'			=> [
            'fa'        => 'gears',
            'label'     => 'Instellingen',
        ],
        'intersystems'		=> [
            'fa'        => 'share-alt',
            'label'     => 'InterSysteem',
        ],
        'apikeys'			=> [
            'fa'        => 'key',
            'label'     => 'Apikeys',
        ],
        'export'			=> [
            'fa'        => 'download',
            'label'     => 'Export',
        ],
        'autominlimit'		=> [
            'fa'        => 'arrows-v',
            'label'     => 'Auto Min Limiet',
        ],
        'mass_transaction'	=> [
            'fa'        => 'exchange',
            'label'     => 'Massa-Transactie',
        ],
        'logs'				=> [
            'fa'        => 'history',
            'label'     => 'Logs',
        ],
        'divider_1'     => [
            'divider'   => true,
        ],
        'users_mode'    => [
            'fa'            => 'user',
            'label'         => 'Leden modus',
            'role_short'    => 'u',
            'role'          => 'user',
        ],
        'guest_mode'    => [
            'fa'            => 'user',
            'label'         => 'Gast modus',
            'role_short'    => 'g',
            'guest'         => 'guest',
        ],
    ];
}
