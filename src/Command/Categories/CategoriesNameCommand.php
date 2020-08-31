<?php declare(strict_types=1);

namespace App\Command\Categories;

use App\Validator\Category\CategoryUniqueName;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CategoriesNameCommand
{
    public $name;
    public $id;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('categories', new Sequentially([
            'constraints' => [
                new NotBlank(),
                new Length(['min' => 1, 'max' => 40]),
            ],
        ]));
        $metadata->addConstraint(new CategoryUniqueName(['groups' => ['unique_name']]));
        $metadata->setGroupSequence(['CategoriesNameCommand', 'unique_name']);
    }
}
