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
			new TwigFilter('replace_when_zero', [$this, 'replace_when_zero']),
		];
	}

	public function getFunctions():array
	{
		return [
			new TwigFunction('encore_entry_script_tags', [$this, 'encore_entry_script_tags']),
			new TwigFunction('encore_entry_link_tags', [$this, 'encore_entry_link_tags']),
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

	public function encore_entry_script_tags():string
	{
		return '';
	}

	public function encore_entry_link_tags():string
	{
		return '';
	}
}
