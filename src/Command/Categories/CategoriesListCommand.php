<?php declare(strict_types=1);

namespace App\Command\Categories;

use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class CategoriesListCommand
{
    #[Sequentially(constraints: [
        new NotBlank(groups: ['edit']),
        new Json(groups: ['edit']),
    ])]
    public $categories;
}
