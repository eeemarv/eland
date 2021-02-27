<?php declare(strict_types=1);

namespace App\Twig;

use App\Render\HeadingRender;
use Twig\Extension\RuntimeExtensionInterface;

class HeadingRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected HeadingRender $heading_render
	)
	{
	}

	public function get_h1():string
	{
		return $this->heading_render->get_h1();
	}

	public function get_sub():string
	{
		return $this->heading_render->get_sub();
	}
}
