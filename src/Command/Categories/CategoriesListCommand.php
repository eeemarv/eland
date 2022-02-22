<?php declare(strict_types=1);

namespace App\Command\Categories;

use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class CategoriesListCommand
{
    #[Sequentially([
        new NotBlank(),
        new Json(),
    ])]
    public $categories;
}
