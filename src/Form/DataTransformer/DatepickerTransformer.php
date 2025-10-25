<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Service\DateFormatService;
use App\Service\PageParamsService;
use Symfony\Component\Form\DataTransformerInterface;

class DatepickerTransformer implements DataTransformerInterface
{
  public function __construct(
    private readonly DateFormatService $date_format_service,
    private readonly PageParamsService $pp,
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

    return $this->date_format_service->reverse($input, $this->pp->schema());
  }
}