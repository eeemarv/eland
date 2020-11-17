<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AccountExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('account', [AccountRuntime::class, 'get']),
			new TwigFunction('user_fullname', [AccountRuntime::class, 'get_fullname']),
			new TwigFunction('username', [AccountRuntime::class, 'get_name']),
			new TwigFunction('account_code', [AccountRuntime::class, 'get_code']),
			new TwigFunction('account_balance', [AccountRuntime::class, 'get_balance']),
		];
	}
}
