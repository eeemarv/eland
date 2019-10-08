<?php declare(strict_types=1);

namespace App\Service;

class token
{
	protected $length = 12;
	protected $hyphen_chance = 2;
	protected $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
	protected $chars_h;
	protected $chars_len;
	protected $chars_h_len;

	public function __construct()
	{
		$this->set_len();
	}

	protected function set_len():self
	{
		$this->chars_h = str_repeat('-', $this->hyphen_chance) . $this->chars;
		$this->chars_len = strlen($this->chars) - 1;
		$this->chars_h_len = strlen($this->chars_h) - 1;

		return $this;
	}

	public function set_length(int $length):self
	{
		$this->length = $length;

		return $this;
	}

	public function set_hyphen_chance(int $hyphen_chance):self
	{
		$this->hyphen_chance = $hyphen_chance;

		$this->set_len();

		return $this;
	}

	public function set_chars(string $chars):self
	{
		$this->chars = $chars;

		$this->set_len();

		return $this;
	}

	public function gen():string
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
