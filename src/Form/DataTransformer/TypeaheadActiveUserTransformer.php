<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Repository\UserRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class TypeaheadActiveUserTransformer implements DataTransformerInterface
{
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

    public function transform($id)
    {
        if (null === $id)
        {
            return '';
        }

        $account_str = $this->user_repository->get_account_str($id, $this->pp->schema());

        return $account_str;
    }

    public function reverseTransform($account_str)
    {
        if (!$account_str)
        {
            return;
        }

        list($code) = explode(' ', $account_str);

        $id = $this->user_repository->get_by_code($code, $this->pp->schema());

        if (!$id)
        {
            return;
        }

        return $id;
    }
}