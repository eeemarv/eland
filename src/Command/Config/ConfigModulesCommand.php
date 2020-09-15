<?php declare(strict_types=1);

namespace App\Command\Config;

use Symfony\Component\Validator\Mapping\ClassMetadata;

class ConfigModulesCommand
{
    public $forum_enabled;
    public $contact_form_enabled;
    public $register_form_enabled;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
    }
}
