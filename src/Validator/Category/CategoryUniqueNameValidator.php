<?php declare(strict_types=1);

namespace App\Validator\Category;

use App\Command\Categories\CategoriesNameCommand;
use App\Repository\CategoryRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class CategoryUniqueNameValidator extends ConstraintValidator
{
    public function __construct(
        protected CategoryRepository $category_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function validate($categories_name_command, Constraint $constraint)
    {
        if (!$constraint instanceof CategoryUniqueName)
        {
            throw new UnexpectedTypeException($constraint, CategoryUniqueName::class);
        }

        if (!$categories_name_command instanceof CategoriesNameCommand)
        {
            throw new UnexpectedTypeException($categories_name_command, CategoriesNameCommand::class);
        }

        $name = $categories_name_command->name;
        $id = $categories_name_command->id;

        $is_unique = $this->category_repository->is_unique_name_except_id($name, $id, $this->pp->schema());

        if (!$is_unique)
        {
            $this->context->buildViolation('category.name_not_unique')
                ->atPath('name')
                ->addViolation();
            return;
        }
    }
}