<?php declare(strict_types=1);

namespace App\Command\Docs;

use App\Validator\DocMap\UniqueDocMap;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class DocsMapEditCommand
{
    public $name;
    public $id;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new NotBlank());
        $metadata->addConstraint(new UniqueDocMap(['groups' => ['Unique']]));
        $metadata->setGroupSequence(['DocsMapEditCommand', 'Unique']);
    }
}
