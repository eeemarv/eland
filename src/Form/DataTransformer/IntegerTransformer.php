<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Repository\UserRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class IntegerTransformer implements DataTransformerInterface
{
    public function __construct(
        protected UserRepository $user_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function transform($id): mixed
    {
        /*
        if (null === $id)
        {
            return '';
        }
        */

        return $id;
    }

    public function reverseTransform($value): mixed
    {
        if (!isset($value) || $value === '')
        {
            return null;
        }

        if (!ctype_digit(ltrim($value, '-')))
        {
            throw new TransformationFailedException('The value ' . $value . ' is not an integer.');
        }

        return (int) $value;
    }
}
