<?php declare(strict_types=1);

namespace App\Render;

use App\Cache\UserCache;

class AccountStrRender
{
	public function __construct(
		protected UserCache $user_cache
	)
	{
	}

	public function get(int $id, string $schema):string
	{
		$user = $this->user_cache->get($id, $schema);

		$code = $user['code'] ?? '';
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
