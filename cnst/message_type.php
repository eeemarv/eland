<?php declare(strict_types=1);

namespace cnst;

class message_type
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

    const TO_CAT_STAT_COLUMN = [
        'offer'     => 'stat_msgs_offers',
        'want'      => 'stat_msgs_wanted',
    ];
}
