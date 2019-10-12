<?php declare(strict_types=1);

namespace App\Twig;

use App\Render\BtnNavRender;
use Twig\Extension\RuntimeExtensionInterface;

class BtnNavRuntime implements RuntimeExtensionInterface
{
	protected $btn_nav_render;

	public function __construct(BtnNavRender $btn_nav_render)
	{
		$this->btn_nav_render = $btn_nav_render;
	}

	public function get():string
	{
		$btn_nav = $this->btn_nav_render->get();

		if ($btn_nav)
		{
			return '<div class="pull-right">' . $btn_nav . '</div>';
		}

		return '';
	}
}
