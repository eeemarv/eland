<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\DateFormatCache;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateFormatExtension extends AbstractExtension
{
	private $dateFormatCache;
	private $format = [];

	public function __construct(
		DateFormatCache $dateFormatCache
	)
	{
		$this->dateFormatCache = $dateFormatCache;	
	}

    public function getFilters()
    {
        return [
			new TwigFilter('date_format', [$this, 'get'], [
				'needs_context'		=> true,
			]),         
        ];
    }	

	public function get(array $context, string $ts, string $precision):string
	{
		$time = strtotime($ts . ' UTC');

		if (!isset($this->format[$precision]))
		{
			if (isset($context['app']))
			{
				$request = $context['app']->getRequest();
				$locale = $request->getLocale();
				$schema = $request->attributes->get('schema');
			}
			else
			{
				$locale = $context['_locale'];
				$schema = $context['schema'];
			}

			$this->format[$precision] = $this->dateFormatCache
				->get($precision, $locale, $schema);
		}

		return strftime($this->format[$precision], $time);
	}

	public function getFormat(array $context, string $precision):string 
	{
		if (!isset($this->format[$precision]))
		{
			$request = $context['app']->getRequest();
			$locale = $request->getLocale();
			$schema = $request->attributes->get('schema');

			$this->format[$precision] = $this->dateFormatCache
				->get($precision, $locale, $schema);
		}

		return $this->format[$precision];
	}
}
