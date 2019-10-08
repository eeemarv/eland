<?php declare(strict_types=1);

namespace App\Twig;

class r_default
{
	protected $r_default;

	public function __construct(
		string $r_default
	)
	{
		$this->r_default = $r_default;
	}

	public function get():string
	{
		return $this->r_default;
	}
}
