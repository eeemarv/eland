<?php declare(strict_types=1);

namespace App\Enum;

enum ImageSizeEnum:string
{
    case TH = 'th';
    case XS = 'xs';
    case SM = 'sm';
    case MD = 'md';
    case LG = 'lg';
    case XL = 'xl';

    public static function values(): array
    {
       return array_column(self::cases(), 'value');
    }
}
