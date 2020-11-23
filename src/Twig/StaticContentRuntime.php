<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PageParamsService;
use App\Service\StaticContentService;
use Twig\Extension\RuntimeExtensionInterface;

class StaticContentRuntime implements RuntimeExtensionInterface
{
	protected PageParamsService $pp;
	protected StaticContentService $static_content_service;

	public function __construct(
		PageParamsService $pp,
		StaticContentService $static_content_service
	)
	{
		$this->pp = $pp;
		$this->static_content_service = $static_content_service;
	}

	public function has(bool $with_role, bool $with_route, string $block):bool
	{
		if ($this->static_content_service->get($with_role ? $this->pp->role() : '', $with_route ? $this->pp->route() : '', $block, $this->pp->schema()) !== '')
		{
			return true;
		}

		if (!$this->pp->edit_en())
		{
			return false;
		}

		if ($this->pp->edit_role_en() xor $with_role)
		{
			return false;
		}

		if ($this->pp->edit_route_en() xor $with_route)
		{
			return false;
		}

		return true;
	}

	public function get(bool $with_role, bool $with_route, string $block):string
	{
		return $this->static_content_service->get(
			$with_role ? $this->pp->role() : '',
			$with_route ? $this->pp->route() : '',
			$block,
			$this->pp->schema()
		);
	}
}
