<?php declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Translation\TranslatorInterface;
use App\Twig\DateFormatExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DatepickerExtension extends AbstractExtension
{
	private $dateFormatExtension;
	private $translator;

	private $formatSearch = ['%e', '%d', '%m', '%Y', '%b', '%B', '%a', '%A'];
	private $formatReplace = ['d', 'dd', 'mm', 'yyyy', 'M', 'MM', 'D', 'DD'];
	private $placeholderReplace = [];
	private $placeholder;

	public function __construct(
		DateFormatExtension $dateFormatExtension,
		TranslatorInterface $translator	
	)
	{
		$this->dateFormatExtension = $dateFormatExtension;
		$this->translator = $translator;	
	}

	public function getFunctions()
    {
        return [
			new TwigFunction('datepicker_format', [$this, 'getFormat'],[
				'needs_context'		=> true,
			]),
            new TwigFunction('datepicker_placeholder', [$this, 'getPlaceholder'], [
				'needs_context'		=> true,
			]),
        ];
    }

	public function getPlaceholder(array $context):string
	{
		$format = $this->dateFormatExtension->getFormat($context, 'day');

		if (!count($this->placeholderReplace))
		{
			foreach($this->formatSearch as $s)
			{
				$this->placeholderReplace[] = $this->translator->trans('datepicker.placeholder.' . ltrim($s, '%'));
			}
		}

		if (!isset($this->placeholder))
		{
			$this->placeholder = str_replace($this->formatSearch, $this->placeholderReplace, $format);
		}

		return $this->placeholder;
	}

	public function getFormat(array $context):string
	{
		$format = $this->dateFormatExtension->getFormat($context, 'day');

		return str_replace($this->formatSearch, $this->formatReplace, $format);
	}
}
