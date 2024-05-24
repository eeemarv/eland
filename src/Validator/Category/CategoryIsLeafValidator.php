<?php declare(strict_types=1);

namespace App\Validator\Category;

use App\Repository\CategoryRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class CategoryIsLeafValidator extends ConstraintValidator
{
    public function __construct(
        protected CategoryRepository $category_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function validate($category_id, Constraint $constraint):void
    {
        if (!$constraint instanceof CategoryIsLeaf)
        {
            throw new UnexpectedTypeException($constraint, CategoryIsLeaf::class);
        }

        if (!isset($category_id) || !$category_id)
        {
            return;
        }

        $filter_options = [
            'options' => ['min_range' => 1],
        ];

        if (!filter_var($category_id, FILTER_VALIDATE_INT, $filter_options))
        {
            throw new UnexpectedTypeException($category_id, 'number');
        }

        $category = $this->category_repository->get((int) $category_id, $this->pp->schema());

        if (($category['left_id'] + 1) !== $category['right_id'])
        {
            $this->context->buildViolation('category.not_leaf')
                ->addViolation();
            return;
        }
    }
}