<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Service\DateFormatService;
use App\Service\PageParamsService;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class ValidityDaysTransformer implements DataTransformerInterface
{
    public function __construct(
        protected DateFormatService $date_format_service,
        protected PageParamsService $pp
    )
    {
    }

    public function transform($expires_at): mixed
    {
        if (null === $expires_at)
        {
            return null;
        }

        $validity_days = (int) round((strtotime($expires_at . ' UTC') - time()) / 86400);
        $validity_days = $validity_days < 1 ? 1 : $validity_days;

        return $validity_days;
    }

    public function reverseTransform($validity_days): mixed
    {
        if ($validity_days === null || !$validity_days)
        {
            return null;
        }

        $validity_days = (int) $validity_days;

        $filter_options = [
            'options' => ['min_range' => 1],
        ];

        if (!filter_var($validity_days, FILTER_VALIDATE_INT, $filter_options))
        {
            throw new TransformationFailedException('No valid input for expires at: ' . $validity_days);
        }

        $expires_at_unix = time() + ((int) $validity_days * 86400);
        $expires_at =  gmdate('Y-m-d H:i:s', $expires_at_unix);

        return $expires_at;
    }
}