<?php declare(strict_types=1);

namespace twig;

use render\pagination as render_pagination;

class pagination
{
	protected $render_pagination;

	public function __construct(render_pagination $render_pagination)
	{
		$this->render_pagination = $render_pagination;
	}

	public function get():string
	{
		return $this->render_pagination->get();
	}
}
