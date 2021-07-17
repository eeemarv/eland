<?php declare(strict_types=1);

namespace App\Validator\User;

use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use App\Validator\User\ActiveUser;

class ActiveUserValidator extends ConstraintValidator
{
    protected CategoryRepository $category_repository;
    protected UserRepository $user_repository;
    protected PageParamsService $pp;

    public function __construct(
        UserRepository $user_repository,
        PageParamsService $pp
    )
    {
        $this->user_repository = $user_repository;
        $this->pp = $pp;
    }

    public function validate($user_id, Constraint $constraint)
    {
        if (!$constraint instanceof ActiveUser)
        {
            throw new UnexpectedTypeException($constraint, ActiveUser::class);
        }

        if (!isset($user_id) || !$user_id)
        {
            return;
        }

        $filter_options = [
            'options' => ['min_range' => 1],
        ];

        if (!filter_var($user_id, FILTER_VALIDATE_INT, $filter_options))
        {
            throw new UnexpectedTypeException($user_id, 'number');
        }

        $is_active = $this->user_repository->is_active((int) $user_id, $this->pp->schema());

        if (!$is_active)
        {
            $this->context->buildViolation('user.not_active')
                ->addViolation();
            return;
        }
    }
}