<?php declare(strict_types=1);

namespace App\Command\Docs;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class DocsCommand implements CommandInterface
{
    public $file_location;
    public $original_filename;
    public $file;
    public $name;
    public $map_name;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('access', new NotBlank([
            'groups'    => ['add', 'edit'],
        ]));
        $metadata->addPropertyConstraint('file', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new File(['maxSize' => '10M']),
            ],
            'groups'    => ['add']
        ]));
    }
}
