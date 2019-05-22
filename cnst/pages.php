<?php

namespace cnst;

class pages
{
    const DEFAULT_VIEW = [
        'users'		=> 'list',
        'messages'	=> 'extended',
        'news'		=> 'extended',
    ];

    const INTERSYSTEM_LANDING = [
        'messages'		=> true,
        'users'			=> true,
        'transactions'	=> true,
        'news'			=> true,
        'docs'			=> true,
    ];
}
