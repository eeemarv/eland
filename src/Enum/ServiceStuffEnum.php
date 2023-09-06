<?php declare(strict_types=1);

namespace App\Enum;

enum ServiceStuffEnum:string
{
    case SERVICE = 'service';
    case STUFF = 'stuff';

    public function get_label():string
    {
        return self::get_label_for($this);
    }

    public static function get_label_for(ServiceStuffEnum $srst):string
    {
        return match($srst)
        {
            self::SERVICE   => 'service_stuff.service.label',
            self::STUFF     => 'service_stuff.stuff.label',
        };
    }

    public static function values(): array
    {
       return array_column(self::cases(), 'value');
    }
}
