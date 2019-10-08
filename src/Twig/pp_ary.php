<?php declare(strict_types=1);

namespace twig;

class pp_ary
{
	protected $pp_ary;

	public function __construct(
		array $pp_ary
	)
	{
		$this->pp_ary = $pp_ary;
	}

	public function get():array
	{
		return $this->pp_ary;
	}
}
