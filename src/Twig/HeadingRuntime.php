<?php declare(strict_types=1);

namespace App\Twig;

use App\Render\HeadingRender;
use Twig\Extension\RuntimeExtensionInterface;

class HeadingRuntime implements RuntimeExtensionInterface
{
	protected $heading_render;

	public function __construct(HeadingRender $heading_render)
	{
		$this->heading_render = $heading_render;
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
