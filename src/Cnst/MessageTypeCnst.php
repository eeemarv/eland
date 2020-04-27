<?php declare(strict_types=1);

namespace App\Cnst;

class MessageTypeCnst
{
    const TO_DB = [
        'offer'     => 1,
        'want'      => 0,
    ];

    const FROM_DB = [
        0   => 'want',
        1   => 'offer',
    ];

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
}
