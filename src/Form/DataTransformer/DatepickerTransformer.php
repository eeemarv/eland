<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Twig\DateFormatExtension;

class DatepickerTransformer implements DataTransformerInterface
{
    private $context;

    public function __construct(RequestStack $requestStack, DateFormatExtension $dateFormat)
    {
        $this->dateFormat = $dateFormat;
        $request = $requestStack->getCurrentRequest();
        $this->context = $request->attributes->all();
    }

    public function transform($date)
    {
        if (null === $date)
        {
            return '';
        }

        return $this->dateFormat->get($this->context, $date, 'day');
    }

    public function reverseTransform($inputDate)
    {
        if (!$inputDate)
        {
            return;
        }

        $parsed = strptime($inputDate, $this->dateFormat->getFormat($this->context, 'day'));

        if ($parsed === false)
        {
            throw new TransformationFailedException(sprintf(
                'User input "%s" could not be parsed to a date',
                $input_date
            ));
        }

        $year = $parsed['tm_year'] + 1900;
        $month = $parsed['tm_mon'] + 1;
        $day = $parsed['tm_mday'];
        $hour = $parsed['tm_hour'];
        $min = $parsed['tm_min'];
        $sec = $parsed['tm_sec'];

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $min, $sec);
    }
}