<?php

namespace cnst;

class status
{
    const LABEL_ARY = [
        0	=> 'Gedesactiveerd',
        1	=> 'Actief',
        2	=> 'Uitstapper',
        //3	=> 'Instapper',    // not used
        //4 => 'Secretariaat, // not used
        5	=> 'Info-pakket',
        6	=> 'Info-moment',
        7	=> 'Extern',
    ];

    const CLASS_ARY = [
        0 => 'inactive',
        2 => 'danger',
        3 => 'success',
        5 => 'warning',
        6 => 'info',
        7 => 'extern',
    ];
}