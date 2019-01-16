<?php

namespace util;

class cnst
{
    const ROLE_ARY = [
        'admin'		=> 'Admin',
        'user'		=> 'User',
        //'guest'		=> 'Guest', //is not a primary role, but a speudo role
        'interlets'	=> 'InterSysteem',
    ];

    const STATUS_ARY = [
        0	=> 'Gedesactiveerd',
        1	=> 'Actief',
        2	=> 'Uitstapper',
        //3	=> 'Instapper',    // not used in selector
        //4 => 'Secretariaat, // not used
        5	=> 'Info-pakket',
        6	=> 'Info-moment',
        7	=> 'Extern',
    ];

    const ACCESS_ARY = [
        'admin'		=> 0,
        'user'		=> 1,
        'guest'		=> 2,
        'anonymous'	=> 3,
    ];

    const INTERSYSTEM_LANDING_PAGES = [
        'messages'		=> true,
        'users'			=> true,
        'transactions'	=> true,
        'news'			=> true,
        'docs'			=> true,
    ];

    const ELAS_CACHE_KEY = [
        'last_fetch'        => 'elas_interlets_last_fetch',
        'apikey_fails'      => 'elas_interlets_apikey_fails',
        'domains'           => 'elas_interlets_domains',
    ];
}
