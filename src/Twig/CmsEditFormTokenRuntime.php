<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\CmsEditFormTokenService;
use Twig\Extension\RuntimeExtensionInterface;

class CmsEditFormTokenRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected CmsEditFormTokenService $cms_edit_form_token_service
	)
	{
	}

	public function get()
	{
		return $this->cms_edit_form_token_service->get();
	}
}
