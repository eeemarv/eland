<?php
namespace service;

class password_strength
{
	public function __contruct()
	{
	}

	public function get(string $password, string $username = ''):int
	{

        $password = str_replace($username, '', $password);

		$strength = 0;
		$password_length = strlen($password);

		if ($password_length < 5)
		{
			return $strength;
		}
		else
		{
			$strength = $password_length * 9;
		}

		for ($i = 2; $i <= 4; $i++)
		{
			$temp = str_split($password, $i);

			$strength -= (ceil($password_length / $i) - count(array_unique($temp)));
		}

		preg_match_all('/[0-9]/', $password, $numbers);

		if (!empty($numbers))
		{
			$numbers = count($numbers[0]);

			if ($numbers >= 1)
			{
				$strength += 8;
			}
		}
		else
		{
			$numbers = 0;
		}

		preg_match_all('/[|!@#$%&*\/=?,;.:\-_+~^¨\\\]/', $password, $symbols);

		if (!empty($symbols))
		{
			$symbols = count($symbols[0]);

			if ($symbols >= 1)
			{
				$strength += 8;
			}
		}
		else
		{
			$symbols = 0;
		}

		preg_match_all('/[a-z]/', $password, $lowercase_characters);
		preg_match_all('/[A-Z]/', $password, $uppercase_characters);

		if (!empty($lowercase_characters))
		{
			$lowercase_characters = count($lowercase_characters[0]);
		}
		else
		{
			$lowercase_characters = 0;
		}

		if (!empty($uppercase_characters))
		{
			$uppercase_characters = count($uppercase_characters[0]);
		}
		else
		{
			$uppercase_characters = 0;
		}

		if (($lowercase_characters > 0) && ($uppercase_characters > 0))
		{
			$strength += 10;
		}

		$characters = $lowercase_characters + $uppercase_characters;

		if (($numbers > 0) && ($symbols > 0))
		{
			$strength += 15;
		}

		if (($numbers > 0) && ($characters > 0))
		{
			$strength += 15;
		}

		if (($symbols > 0) && ($characters > 0))
		{
			$strength += 15;
		}

		if ($strength < 0)
		{
			$strength = 0;
		}

		if ($strength > 100)
		{
			$strength = 100;
		}

		return $strength;
	}
}
