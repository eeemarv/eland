<?php declare(strict_types=1);

namespace App\Command\Docs;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class DocsMapEditCommand
{
    public $name;
    public $id;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new NotBlank());

    }
}
