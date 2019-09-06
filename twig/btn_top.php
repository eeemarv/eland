<?php declare(strict_types=1);

namespace twig;

use render\btn_top as render_btn_top;

class btn_top
{
	protected $render_btn_top;

	public function __construct(render_btn_top $render_btn_top)
	{
		$this->render_btn_top = $render_btn_top;
	}

	public function get():string
	{
		return $this->render_btn_top->get();
	}
}
