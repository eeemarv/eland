<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Service\DateFormatService;
use App\Service\PageParamsService;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class DatepickerDatetimeTransformer implements DataTransformerInterface
{
    public function __construct(
        protected DateFormatService $date_format_service,
        protected PageParamsService $pp
    )
    {
    }

    public function transform($date): mixed
    {
        if (null === $date)
        {
            return '';
        }

        return $this->date_format_service->get($date, 'day', $this->pp->schema());
    }

    public function reverseTransform($input): mixed
    {
        if ($input === null || !$input)
        {
            return null;
        }

        $str_datetime = $this->date_format_service->reverse($input, $this->pp->schema());

        if ($str_datetime === '')
        {
            throw new TransformationFailedException('Failed to transform ' . $input . ' to a valid datetime.');
        }

        return $str_datetime;
    }
}