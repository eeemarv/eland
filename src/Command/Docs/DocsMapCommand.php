<?php declare(strict_types=1);

namespace App\Command\Docs;

use App\Command\CommandInterface;
use App\Validator\DocMap\DocMapUniqueName;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class DocsMapCommand implements CommandInterface
{
    public $name;
    public $id;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new NotBlank());
        $metadata->addConstraint(new DocMapUniqueName(['groups' => ['unique_name']]));
        $metadata->setGroupSequence(['DocsMapCommand', 'unique_name']);
    }
}
