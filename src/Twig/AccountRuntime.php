<?php declare(strict_types=1);

namespace App\Twig;

use App\Repository\AccountRepository;
use App\Service\UserCacheService;
use Twig\Extension\RuntimeExtensionInterface;

class AccountRuntime implements RuntimeExtensionInterface
{
	protected AccountRepository $account_repository;
	protected UserCacheService $user_cache_service;

	public function __construct(
		AccountRepository $account_repository,
		UserCacheService $user_cache_service
	)
	{
		$this->account_repository = $account_repository;
		$this->user_cache_service = $user_cache_service;
	}

	public function get(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		$code = $user['code'] ?? '***';
		$name = $user['name'] ?? '***';
		return htmlspecialchars($code . ' ' . $name);
	}

	public function get_full_name(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['full_name']);
	}

	public function get_name(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['name']);
	}

	public function get_code(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['code']);
	}

	public function get_balance(int $id, string $schema):int
	{
		return $this->account_repository->get_balance($id, $schema);
	}
}
