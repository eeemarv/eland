<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class TagsTransformer implements DataTransformerInterface
{
    public function __construct(
    )
    {
    }

    public function transform($id_ary): mixed
    {
        return implode(',', $id_ary);
    }

    public function reverseTransform($id_str): mixed
    {
        return explode(',', $id_str);
    }
}
