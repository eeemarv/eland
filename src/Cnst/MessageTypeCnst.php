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
            'label'     => 'Niet in- of uitstappers',
            'btn_class' => 'default',
        ],
        'u-new'   => [
            'label'     => 'Instappers',
            'btn_class' => 'success',
        ],
        'u-leaving' => [
            'label'     => 'Uitstappers',
            'btn_class' => 'danger',
        ],
    ];
}
