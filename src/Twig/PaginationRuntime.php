<?php declare(strict_types=1);

namespace App\Twig;

use App\Render\PaginationRender;
use Twig\Extension\RuntimeExtensionInterface;

class PaginationRuntime implements RuntimeExtensionInterface
{
	protected $pagination_render;

	public function __construct(PaginationRender $pagination_render)
	{
		$this->pagination_render = $pagination_render;
	}

	public function get():string
	{
		return $this->pagination_render->get();
	}
}
