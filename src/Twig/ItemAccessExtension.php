<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\ItemAccessService;
use Twig\Attribute\AsTwigFunction;

class ItemAccessExtension
{
	public function __construct(
		private readonly ItemAccessService $item_access_service
	)
	{
	}

  #[AsTwigFunction(name: 'item_visible')]
	public function item_visible(string $access):bool
	{
		return $this->item_access_service->is_visible($access);
	}
}
