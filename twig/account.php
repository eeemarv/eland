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

	public function get(int $id, string $schema):string
	{
		$user = $this->user_cache->get($id, $schema);
		return htmlspecialchars($user['letscode'] . ' ' . $user['name']);
	}

	public function get_fullname(int $id, string $schema):string
	{
		$user = $this->user_cache->get($id, $schema);
		return htmlspecialchars($user['fullname']);
	}

	public function get_name(int $id, string $schema):string
	{
		$user = $this->user_cache->get($id, $schema);
		return htmlspecialchars($user['name']);
	}

	public function get_code(int $id, string $schema):string
	{
		$user = $this->user_cache->get($id, $schema);
		return htmlspecialchars($user['letscode']);
	}

	public function get_balance(int $id, string $schema):int
	{
		$user = $this->user_cache->get($id, $schema);
		return $user['saldo'];
	}

	public function get_min(int $id, string $schema):string
	{
		$minlimit = $this->user_cache->get($id, $schema)['minlimit'];
		$minlimit = $minlimit == -999999999 ? '' : $minlimit;
		return $minlimit;
	}

	public function get_max(int $id, string $schema):string
	{
		$maxlimit = $this->user_cache->get($id, $schema)['maxlimit'];
		$maxlimit = $maxlimit == 999999999 ? '' : $maxlimit;
		return $maxlimit;
	}

}
