<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class S3UrlExtension extends AbstractExtension
{
	public function getFilters():array
	{
		return [
			new TwigFilter('s3', [S3UrlRuntime::class, 'get_a']),
		];
	}

	public function getFunctions():array
	{
		return [
			new TwigFunction('s3', [S3UrlRuntime::class, 'get_url']),
		];
	}
}
