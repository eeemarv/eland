<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class SimpleTextExtension extends AbstractExtension
{
	public function getFilters():array
	{
		return [
			new TwigFilter('underline', [$this, 'underline']),
		];
	}

	public function getFunctions():array
	{
		return [
		];
	}

	public function underline(string $input, string $char = '-'):string
	{
		$len = strlen($input);
		return $input . "\r\n" . str_repeat($char, $len);
	}
}
