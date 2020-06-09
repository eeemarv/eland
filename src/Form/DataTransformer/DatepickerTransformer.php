<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Service\DateFormatService;
use App\Service\PageParamsService;
use Symfony\Component\Form\DataTransformerInterface;

class DatepickerTransformer implements DataTransformerInterface
{
    protected $date_format_service;
    protected $pp;

    public function __construct(
        DateFormatService $date_format_service,
        PageParamsService $pp
    )
    {
        $this->date_format_service = $date_format_service;
        $this->pp = $pp;
    }

    public function transform($date)
    {
        if (null === $date)
        {
            return '';
        }

        return $this->date_format_service->get($date, 'day', $this->pp->schema());
    }

    public function reverseTransform($input)
    {
        if ($input === null || !$input)
        {
            return;
        }

        return $this->date_format_service->reverse($input, $this->pp->schema());
    }
}