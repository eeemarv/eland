<?php declare(strict_types=1);

namespace App\Command\Docs;

use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class DocsAddCommand
{
    public $file;
    public $name;
    public $map_name;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('access', new NotBlank());
        $metadata->addPropertyConstraint('file', new Sequentially([
            new NotBlank(),
            new File(['maxSize' => '10M']),
        ]));
    }
}
