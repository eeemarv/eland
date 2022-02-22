<?php declare(strict_types=1);

namespace App\Command\Categories;

use App\Validator\Category\CategoryUniqueName;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

#[CategoryUniqueName(groups: ['unique_name'])]
#[GroupSequence(['CategoriesNameCommand', 'unique_name'])]
class CategoriesNameCommand
{
    #[Sequentially([
        new NotBlank(),
        new Length(min: 1, max: 40),
    ])]
    public $name;

    public $id;
}
