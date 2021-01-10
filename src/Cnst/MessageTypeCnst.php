<?php declare(strict_types=1);

namespace App\Cnst;

class MessageTypeCnst
{
    const TO_LABEL = [
        'offer'     => 'aanbod',
        'want'      => 'vraag',
    ];

    const TO_THE_LABEL = [
        'offer'     => 'het aanbod',
        'want'      => 'de vraag',
    ];

    const TO_THIS_LABEL = [
        'offer'     => 'dit aanbod',
        'want'      => 'deze vraag',
    ];

    const OFFER_WANT_TPL_ARY = [
        'offer'     => [
            'label'   => 'Aanbod',
            'btn_class' => 'default',
        ],
        'want'      => [
            'label'   => 'Vraag',
            'btn_class' => 'default-2',
        ],
    ];

    const SERVICE_STUFF_TPL_ARY = [
        'service'   => [
            'label'     => 'Diensten',
            'btn_class' => 'default',
        ],
        'stuff'     => [
            'label'     => 'Spullen',
            'btn_class' => 'default-2',
        ],
        'null-service-stuff' => [
            'label'     => 'D/S onbepaald',
            'btn_class' => 'danger',
            'title'     => 'Diensten of spullen onbepaald',
        ],
    ];

    const VALID_EXPIRED_TPL_ARY = [
        'valid' => [
            'label'     => 'Geldig',
            'btn_class' => 'default',
        ],
        'expired'   => [
            'label'     => 'Vervallen',
            'btn_class' => 'danger',
        ],
    ];

    const USERS_TPL_ARY = [
        'u-active' => [
            'label'     => 'Actief',
            'btn_class' => 'default',
            'title'     => 'Van actieve gebruikers, niet in- of uitstappend'
        ],
        'u-new'   => [
            'label'     => 'Instappers',
            'btn_class' => 'success',
            'title'     => 'Van nieuwe gebruikers',
        ],
        'u-leaving' => [
            'label'     => 'Uitstappers',
            'btn_class' => 'danger',
            'title'     => 'Van uitstappende gebruikers',
        ],
    ];
}
