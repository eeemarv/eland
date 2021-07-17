<?php declare(strict_types=1);

namespace App\Command\MessagesModules;

use Symfony\Component\Validator\Mapping\ClassMetadata;

class MessagesModulesCommand
{
    public $service_stuff_enabled;
    public $category_enabled;
    public $expires_at_enabled;
    public $units_enabled;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
    }
}
