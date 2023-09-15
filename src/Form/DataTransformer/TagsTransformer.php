<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

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

    public function reverseTransform($str_ids): mixed
    {
        if (!isset($str_ids))
        {
            return [];
        }

        if (empty($str_ids))
        {
            return [];
        }

        $str_id_ary = explode(',', $str_ids);
        $id_ary = [];

        foreach ($str_id_ary as $str_id)
        {
            if (!ctype_digit($str_id))
            {
                throw new TransformationFailedException('Transformation failed of value ' . $str_ids);
            }

            $id_ary[] = (int) $str_id;
        }

        return $id_ary;
    }
}
