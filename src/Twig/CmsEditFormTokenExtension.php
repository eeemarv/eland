<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CmsEditFormTokenExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('cms_edit_form_token', [CmsEditFormTokenRuntime::class, 'get']),
		];
	}
}
