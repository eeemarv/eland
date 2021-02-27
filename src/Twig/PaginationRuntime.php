<?php declare(strict_types=1);

namespace App\Twig;

use App\Render\PaginationRender;
use Twig\Extension\RuntimeExtensionInterface;

class PaginationRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected PaginationRender $pagination_render
	)
	{
	}

	public function get():string
	{
		return $this->pagination_render->get();
	}
}
