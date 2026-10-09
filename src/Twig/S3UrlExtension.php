<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

class S3UrlExtension
{
	public function __construct(
		private readonly string $env_s3_url
	)
	{
	}

  #[AsTwigFunction(name: 's3')]
	public function get_url(
		string $file = '',
	):string
	{
		return $this->env_s3_url . $file;
	}

  #[AsTwigFilter(name: 's3')]
	public function get_a(
		string $label,
		string $file,
	):string
	{
		$out = '<a href="';
		$out .= $this->env_s3_url . $file;
		$out .= '">';
		$out .= htmlspecialchars($label);
		$out .= '</a>';
		return $out;
	}
}
