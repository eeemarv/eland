<?php

namespace twig;

use service\user_cache;

class account
{
	protected $user_cache;

	public function __construct(user_cache $user_cache)
	{
		$this->user_cache = $user_cache;
	}

	public function get(int $id, string $schema)
	{
		$user = $this->user_cache->get($id, $schema);
		return htmlspecialchars($user['letscode'] . ' ' . $user['name']);
	}
}
