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

    const CLASS_ARY = [
        0 => 'bg-secondary-li',
        2 => 'bg-danger-li',
        3 => 'bg-success-li',
        5 => 'bg-warning-li',
        6 => 'bg-info-li',
        7 => 'bg-extern-li',
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
