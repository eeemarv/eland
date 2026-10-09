<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\ItemAccessService;
use Twig\Attribute\AsTwigFilter;

class AccessExtension
{
	public function __construct(
		private readonly ItemAccessService $item_access_service,
	)
	{
	}

  #[AsTwigFilter(name: 'is_visible')]
	public function is_visible(
    string $item_access,
  ):bool
	{
    return $this->item_access_service->is_visible($item_access);
	}
}
