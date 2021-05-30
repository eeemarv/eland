<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\ItemAccessService;
use Twig\Extension\RuntimeExtensionInterface;

class ItemAccessRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected ItemAccessService $item_access_service
	)
	{
	}

	public function item_visible(string $access):bool
	{
		return $this->item_access_service->is_visible($access);
	}
}
