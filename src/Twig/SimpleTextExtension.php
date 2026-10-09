<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

class SimpleTextExtension
{
  #[AsTwigFilter(name: 'underline')]
	public function underline(string $input, string $char = '-'):string
	{
		$len = strlen($input);
		return $input . "\r\n" . str_repeat($char, $len);
	}

  #[AsTwigFilter(name: 'replace_when_zero')]
	public function replace_when_zero(int $input, $replace = null):string
	{
		return $input === 0 ? $replace : $input;
	}
}
