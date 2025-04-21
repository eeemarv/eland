<?php declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

class UuidService
{
	const BASE58_CHARS = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	public function gen_bin():string
	{
		$rnd = random_bytes(16);

		$rnd[6] = chr(ord($rnd[6]) & 0x0f | 0x40); // set version to 0100
		$rnd[8] = chr(ord($rnd[8]) & 0x3f | 0x80); // set bits 6-7 to 10

		return $rnd;
	}

	public function bin_to_uuid(string $bin):string
	{
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bin), 4));
	}

	public function uuid_to_bin(string $uuid):string
	{
		return hex2bin(str_replace('-', '', $uuid));
	}

	public function bin_to_base58(string $bin):string
	{
		$num = gmp_init(bin2hex($bin), 16);
		$res = '';

		while (gmp_cmp($num, 0) > 0)
		{
			[$num, $rem] = [gmp_div_q($num, 58), gmp_intval(gmp_mod($num, 58))];
			$res .= self::BASE58_CHARS[$rem];
		}

		return strrev($res);
	}

	public function base58_to_bin(string $base58):string
	{
		$char_map = array_flip(str_split(self::BASE58_CHARS));
		$num = gmp_init(0);

		foreach (str_split($base58) as $char)
		{
			if (!isset($char_map[$char]))
			{
				throw new InvalidArgumentException('Invalid Base58 character: ' . $char);
			}

			$num = gmp_add(gmp_mul($num, 58), $char_map[$char]);
		}

		$bin = gmp_export($num);
		return str_pad($bin, 16, "\0", STR_PAD_LEFT);
	}

	public function gen_uuid():string
	{
		return $this->bin_to_uuid($this->gen_bin());
	}

	public function gen_base58():string
	{
		return $this->bin_to_base58($this->gen_bin());
	}
}
