<?php declare(strict_types=1);

namespace App\Twig;

use App\Render\BtnTopRender;
use Twig\Extension\RuntimeExtensionInterface;

class BtnTopRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected BtnTopRender $btn_top_render
	)
	{
	}

	public function get():string
	{
		return $this->btn_top_render->get();
	}
}
