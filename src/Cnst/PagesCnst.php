<?php declare(strict_types=1);

namespace App\Cnst;

class PagesCnst
{
    const CMS_TOKEN = 'a--cms-token';

    const LANDING = [
        'messages'		=> true,
        'users'			=> true,
        'transactions'	=> true,
        'news'			=> true,
        'docs'			=> true,
        'forum'         => true,
    ];

    const DEFAULT_VIEW = [
        'users'		=> 'list',
        'messages'	=> 'extended',
        'news'		=> 'extended',
    ];

    const ROUTE_TO_VIEW = [
        'users_list'        => ['users', 'list'],
        'users_tiles'       => ['users', 'tiles'],
        'users_map'         => ['users', 'map'],
        'messages_list'     => ['messages', 'list'],
        'messages_extended' => ['messages', 'extended'],
        'messages_list_self'        => ['messages', 'list'],
        'messages_extended_self'    => ['messages', 'extended'],
        'news_list'         => ['news', 'list'],
        'news_extended'     => ['news', 'extended'],
    ];
}
