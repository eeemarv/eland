<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\ImageTokenService;
use Twig\Extension\RuntimeExtensionInterface;

class ImageTokenRuntime implements RuntimeExtensionInterface
{
	protected ImageTokenService $image_token_service;

	public function __construct(
		ImageTokenService $image_token_service
	)
	{
		$this->image_token_service = $image_token_service;
	}

	public function gen(int $id, string $schema):string
	{
		return $this->image_token_service->gen($id, $schema);
	}
}
