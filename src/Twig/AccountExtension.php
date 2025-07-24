<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AccountExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('account', [AccountRuntime::class, 'get'], ['needs_context' => true]),
			new TwigFunction('user_full_name', [AccountRuntime::class, 'get_full_name'], ['needs_context' => true]),
			new TwigFunction('username', [AccountRuntime::class, 'get_name'], ['needs_context' => true]),
			new TwigFunction('account_code', [AccountRuntime::class, 'get_code'], ['needs_context' => true]),
			new TwigFunction('account_balance', [AccountRuntime::class, 'get_balance'], ['needs_context' => true]),
			new TwigFunction('account_status', [AccountRuntime::class, 'get_status'], ['needs_context' => true]),
		];
	}
}
