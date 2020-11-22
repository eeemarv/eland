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

	public function has_local(string $block):bool
	{
		if ($this->static_content_service->get('', $this->pp->route(), $block, $this->pp->schema()) !== '')
		{
			return true;
		}

		return $this->pp->edit_local_en();
	}

	public function get_local(string $block):string
	{
		return $this->static_content_service->get('', $this->pp->route(),$block, $this->pp->schema());
	}

	public function has_global(string $block):bool
	{
		if ($this->static_content_service->get('', '', $block, $this->pp->schema()) !== '')
		{
			return true;
		}

		return $this->pp->edit_global_en();
	}

	public function get_global(string $block):string
	{
		return $this->static_content_service->get('', '', $block, $this->pp->schema());
	}
}
