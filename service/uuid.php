<?php

namespace service;

class uuid
{
	public function __construct()
	{
	}

	public function gen()
	{
		$rnd = random_bytes(16);

		$rnd[6] = chr(ord($rnd[6]) & 0x0f | 0x40); // set version to 0100
		$rnd[8] = chr(ord($rnd[8]) & 0x3f | 0x80); // set bits 6-7 to 10

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($rnd), 4));
	}
}

