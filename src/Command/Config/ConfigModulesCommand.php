<?php declare(strict_types=1);

namespace App\Command\Config;

use Symfony\Component\Validator\Mapping\ClassMetadata;

class ConfigModulesCommand
{
    public $forum_en;
    public $contact_form_en;
    public $register_en;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
    }
}
