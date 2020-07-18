<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\ConfigService;
use App\Service\StaticContentService;
use Twig\Extension\RuntimeExtensionInterface;

class StaticContentRuntime implements RuntimeExtensionInterface
{
	protected StaticContentService $static_content_service;

	public function __construct(StaticContentService $static_content_service)
	{
		$this->static_content_service = $static_content_service;
	}

	public function get(string $id, string $block, string $schema)
	{
		return $this->static_content_service->get($id, $block, $schema);
	}
}
