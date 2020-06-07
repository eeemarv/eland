<?php declare(strict_types=1);

namespace App\Command\Docs;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class DocsEditCommand
{
    public $location;
    public $original_filename;
    public $name;
    public $map_name;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('access', new NotBlank());
    }
}
