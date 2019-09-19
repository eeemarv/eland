<?php declare(strict_types=1);

namespace render;

use service\user_cache;

class account_str
{
	protected $user_cache;

	public function __construct(
		user_cache $user_cache
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
