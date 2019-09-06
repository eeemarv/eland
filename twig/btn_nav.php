<?php declare(strict_types=1);

namespace twig;

use render\btn_nav as render_btn_nav;

class btn_nav
{
	protected $render_btn_nav;

	public function __construct(render_btn_nav $render_btn_nav)
	{
		$this->render_btn_nav = $render_btn_nav;
	}

	public function get():string
	{
		$btn_nav = $this->render_btn_nav->get();

		if ($btn_nav)
		{
			return '<div class="pull-right">' . $btn_nav . '</div>';
		}

		return '';
	}
}
