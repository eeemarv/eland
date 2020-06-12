<?php declare(strict_types=1);

namespace App\Validator\Category;

use App\Repository\CategoryRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use App\Validator\Category\Category;

class CategoryValidator extends ConstraintValidator
{
    protected CategoryRepository $category_repository;
    protected PageParamsService $pp;

    public function __construct(
        CategoryRepository $category_repository,
        PageParamsService $pp
    )
    {
        $this->category_repository = $category_repository;
        $this->pp = $pp;
    }

    public function validate($category_id, Constraint $constraint)
    {
        if (!$constraint instanceof Category)
        {
            throw new UnexpectedTypeException($constraint, Category::class);
        }

        if (!isset($category_id) || !$category_id)
        {
            return;
        }

        if (!ctype_digit($category_id))
        {
            throw new UnexpectedTypeException($category_id, 'number');
        }

        $exists = $this->category_repository->exists((int) $category_id, $this->pp->schema());

        if (!$exists)
        {
            $this->context->buildViolation('category.not_exists')
                ->addViolation();
            return;
        }
    }
}