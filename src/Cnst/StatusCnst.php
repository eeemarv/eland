<?php declare(strict_types=1);

namespace App\Cnst;

class StatusCnst
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

    const RENDER_ARY = [
        0	=> 'Gedesactiveerd',
        1	=> 'Actief',
        2	=> 'Uitstapper',
        3	=> 'Instapper',
        //4 => 'Secretariaat, // not used
        5	=> 'Info-pakket',
        6	=> 'Info-moment',
        7	=> 'Extern',
    ];

    const CLASS_ARY = [
        0 => 'inactive',
        2 => 'danger',
        3 => 'success',
        5 => 'info-pack',
        6 => 'info',
        7 => 'warning',
    ];

    const THUMBPRINT_ARY = [
        0 => 'inactive',
        1 => 'active',
        2 => 'active',
        5 => 'ip',
        6 => 'im',
        7 => 'extern'
     ];
}
