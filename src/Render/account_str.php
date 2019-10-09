<?php declare(strict_types=1);

namespace App\Render;

use App\Service\UserCache;

class AccountStr
{
	protected $user_cache;

	public function __construct(
		UserCache $user_cache
	)
	{
		$this->user_cache = $user_cache;
	}

	public function get(int $id, string $schema):string
	{
		$user = $this->user_cache->get($id, $schema);

		$str = trim($user['letscode'] . ' ' . $user['name']);

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
