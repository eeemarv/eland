<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\RuntimeExtensionInterface;

class S3UrlRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected string $env_s3_url
	)
	{
	}

	public function get_url(
		string $file = ''
	):string
	{
		return $this->env_s3_url . $file;
	}

	public function get_a(
		string $label,
		string $file
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
