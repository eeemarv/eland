<?php declare(strict_types=1);

namespace App\Service;

class TokenGeneratorService
{
	const CHARS = '0123456789abcdefghijklmnopqrstuvwxyz';
	const HYPHEN_CHANCE = 2;

	public function gen(int $len = 12):string
	{
		$chars_h = str_repeat('-', self::HYPHEN_CHANCE) . self::CHARS;
		$chars_h_len = strlen($chars_h) - 1;
		$chars_len = strlen(self::CHARS) - 1;

		$token = '';
		$ch = '-';

		for ($j = 0; $j < $len; $j++)
		{
			if ($ch === '-')
			{
				$ch = self::CHARS[random_int(0, $chars_len)];
			}
			else
			{
				$ch = $chars_h[random_int(0, $chars_h_len)];
			}

			$token .= $ch;

			if ($j === $len - 2)
			{
				$ch = '-';
			}
		}

		return $token;
	}
}
