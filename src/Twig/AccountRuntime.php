<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\UserCacheService;
use Twig\Extension\RuntimeExtensionInterface;

class AccountRuntime implements RuntimeExtensionInterface
{
	protected $user_cache_service;

	public function __construct(UserCacheService $user_cache_service)
	{
		$this->user_cache_service = $user_cache_service;
	}

	public function get(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['letscode'] . ' ' . $user['name']);
	}

	public function get_fullname(int $id, string $schema):string
	{
		$user = $this->user_cache_service>get($id, $schema);
		return htmlspecialchars($user['fullname']);
	}

	public function get_name(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['name']);
	}

	public function get_code(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['letscode']);
	}

	public function get_balance(int $id, string $schema):int
	{
		$user = $this->user_cache_service>get($id, $schema);
		return $user['saldo'];
	}

	public function get_min(int $id, string $schema):string
	{
		$minlimit = $this->user_cache_service>get($id, $schema)['minlimit'];
		$minlimit = $minlimit == -999999999 ? '' : $minlimit;
		return $minlimit;
	}

	public function get_max(int $id, string $schema):string
	{
		$maxlimit = $this->user_cache_service>get($id, $schema)['maxlimit'];
		$maxlimit = $maxlimit == 999999999 ? '' : $maxlimit;
		return $maxlimit;
	}
}
