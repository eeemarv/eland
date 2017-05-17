<?php

namespace service;

class token
{
	private $length = 8;
	private $hyphen_chance = 2;
	private $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
	private $chars_h;
	private $chars_len;
	private $chars_h_len;

	public function __construct(int $length = 8, int $hyphen_chance = 2)
	{
		$this->length = $length;
		$this->hyphen_chance = $hyphen_chance;
		$this->set_len();
	}

	private function set_len()
	{
		$this->chars_h = str_repeat('-', $this->hyphen_chance) . $this->chars;
		$this->chars_len = strlen($this->chars) - 1;
		$this->chars_h_len = strlen($this->chars_h) - 1;

		return $this;
	}

	public function set_length(int $length)
	{
		$this->length = $length;

		return $this;
	}

	public function set_hyphen_chance(int $hyphen_chance)
	{
		$this->hyphen_chance = $hyphen_chance;

		$this->set_len();

		return $this;
	}

	public function set_chars(string $chars)
	{
		$this->chars = $chars;

		$this->set_len();

		return $this;
	}

	public function gen()
	{
		$token = '';
		$ch = '-';

		for ($j = 0; $j < $this->length; $j++)
		{
			if ($ch === '-')
			{
				$ch = $this->chars[random_int(0, $this->chars_len)];
			}
			else
			{
				$ch = $this->chars_h[random_int(0, $this->chars_h_len)];
			}

			$token .= $ch;

			if ($j === $this->length - 2)
			{
				$ch = '-';
			}
		}

		return $token;
	}
}

