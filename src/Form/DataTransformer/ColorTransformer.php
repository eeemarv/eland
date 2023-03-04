<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class ColorTransformer implements DataTransformerInterface
{
    public function __construct(
    )
    {
    }

    public function transform($color): mixed
    {
        if (null === $color)
        {
            return '';
        }

        return strtolower($color);
    }

    public function reverseTransform($color): mixed
    {
        if (!$color)
        {
            return null;
        }

        return strtolower(trim($color));
    }
}
