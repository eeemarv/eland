<?php declare(strict_types=1);

namespace App\Model;

abstract class job
{
	public function get_class_name():string
	{
		$full = static::class;
		$pos = strrpos($full, '\\');

		if ($pos)
		{
			return substr($full, $pos + 1);
		}

		return $full;
	}
}
