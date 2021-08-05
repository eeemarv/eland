<?php declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ConfigMap
{
    public function __construct(
        public string $type,
        public string $key
    )
    {
    }
}
