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

	public function get(
		string $file
	):string
	{
		return $this->env_s3_url . $file;
	}

	public function get_link_open(
		string $file
	):string
	{
		$out = '<a href="';
		$out .= $this->get($file);
		$out .= '">';

		return $out;
	}
}
