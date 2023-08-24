<?php declare(strict_types=1);

namespace App\Enum;

enum ImageSizeEnum:string
{
    case XL = 'xl';
    case LG = 'lg';
    case MD = 'md';
    case SM = 'sm';
    case XS = 'xs';
    case TH = 'th';

    public static function values(): array
    {
       return array_column(self::cases(), 'value');
    }
}
