<?php declare(strict_types=1);

namespace App\Render;

use App\Service\UserCacheService;

class AccountStrRender
{
	protected UserCacheService $user_cache_service;

	public function __construct(
		UserCacheService $user_cache_service
	)
	{
		$this->user_cache_service = $user_cache_service;
	}

	public function get(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);

		$code = $user['letscode'] ?? '';
		$name = $user['name'] ?? '';

		$str = trim($code . ' ' . $name);

		return $str === '' ? '** (leeg) ***' : $str;
	}

	public function str(
		int $id,
		string $schema
	):string
	{
		if ($id === 0)
		{
			return '** (id: 0) **';
		}

		return $this->get($id, $schema);
	}

	public function get_with_id(
		int $id,
		string $schema
	):string
	{
		if ($id === 0)
		{
			return '** (id: 0) **';
		}

		return $this->str($id, $schema) . ' (' . $id . ')';
	}
}
