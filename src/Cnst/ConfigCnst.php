<?php declare(strict_types=1);

namespace App\Cnst;

class ConfigCnst
{
    const LANDING_PAGE_OPTIONS = [
        'messages'		=> 'Vraag en aanbod',
        'users'			=> 'Leden',
        'transactions'	=> 'Transacties',
        'news'			=> 'Nieuws',
        'docs'          => 'Documenten',
        'forum'         => 'Forum',
    ];

    const BLOCK_ARY = [
        'messages'		=> [
            'recent'	=> 'Recent vraag en aanbod',
        ],
        'intersystem'	=> [
            'recent'	=> 'Recent interSysteem vraag en aanbod',
        ],
        'forum'			=> [
            'recent'	=> 'Recente forumberichten',
        ],
        'news'			=> [
            'all'		=> 'Alle nieuwsberichten',
            'recent'	=> 'Recente nieuwsberichten',
        ],
        'docs'			=> [
            'recent'	=> 'Recente documenten',
        ],
        'new_users'		=> [
            'all'		=> 'Alle nieuwe leden',
            'recent'	=> 'Recente nieuwe leden',
        ],
        'leaving_users'	=> [
            'all'		=> 'Alle uitstappende leden',
            'recent'	=> 'Recent uitstappende leden',
        ],
        'transactions' => [
            'recent'	=> 'Recente transacties',
        ],
        'messages_self' => [
            'all'       => 'Lijst eigen vraag en aanbod',
        ],
        'mollie'    => [
            'all'       => 'Openstaande Mollie betalingsverzoeken (EUR).
                - Alleen getoond bij openstaande betalingsverzoeken.',
        ],
    ];
}