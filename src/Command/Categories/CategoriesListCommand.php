<?php declare(strict_types=1);

namespace App\Command\Categories;

use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CategoriesListCommand
{
    public $categories;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('categories', new Sequentially([
            'constraints' => [
                new NotBlank(),
                new Json(),
            ],
        ]));
    }
}
