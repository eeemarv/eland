<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\RuntimeExtensionInterface;

class S3UrlRuntime implements RuntimeExtensionInterface
{
	protected string $env_s3_url;

	public function __construct(
		string $env_s3_url
	)
	{
		$this->env_s3_url = $env_s3_url;
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
