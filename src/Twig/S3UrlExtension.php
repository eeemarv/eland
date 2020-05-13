<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class S3UrlExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('s3_url', [S3UrlRuntime::class, 'get']),
			new TwigFunction('s3_link_open', [S3UrlRuntime::class, 'get_link_open']),
		];
	}
}
