<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SimpleTextFilterExtension extends AbstractExtension
{
	public function getFilters():array
	{
		return [
			new TwigFilter('underline', [$this, 'underline']),
			new TwigFilter('replace_when_zero', [$this, 'replace_when_zero']),
			new TwigFilter('json_decode', [$this, 'json_decode']),
		];
	}

	public function underline(string $input, string $char = '-'):string
	{
		$len = strlen($input);
		return $input . "\r\n" . str_repeat($char, $len);
	}

	public function replace_when_zero(int $input, $replace = null):string
	{
		return $input === 0 ? $replace : $input;
	}

	public function json_decode(string $json):array
	{
		return json_decode($json ?: '[]', true);
	}
}
