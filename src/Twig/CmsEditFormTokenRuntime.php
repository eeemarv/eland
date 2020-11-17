<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\CmsEditFormTokenService;
use Twig\Extension\RuntimeExtensionInterface;

class CmsEditFormTokenRuntime implements RuntimeExtensionInterface
{
	protected CmsEditFormTokenService $cms_edit_form_token_service;

	public function __construct(
		CmsEditFormTokenService $cms_edit_form_token_service
	)
	{
		$this->cms_edit_form_token_service = $cms_edit_form_token_service;
	}

	public function get()
	{
		return $this->cms_edit_form_token_service->get();
	}
}
