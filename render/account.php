<?php

namespace render;

use render\link;
use service\systems;
use service\user_cache;

class account
{
	protected $link;
	protected $systems;
	protected $user_cache;

	public function __construct(
		link $link,
		systems $systems,
		user_cache $user_cache
	)
	{
		$this->link = $link;
		$this->systems = $systems;
		$this->user_cache = $user_cache;
	}

	public function get_str(int $id, string $schema):string
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

		return $this->get_str($id, $schema);
	}

	public function str_id(
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

	public function link(
		int $id,
		array $pp_ary
	):string
	{
		$schema = $this->systems->get_schema($pp_ary['system']);

		return $this->link->link_no_attr('users', $pp_ary,
			['id' => $id], $this->get_str($id, $schema));
	}

	public function link_url(
		int $id,
		array $pp_ary
	):string
	{
		$schema = $this->systems->get_schema($pp_ary['system']);

		return $this->link->link_url('users', $pp_ary,
			['id' => $id], $this->get_str($id, $schema), []);
	}

	public function inter_link(
		int $id,
		string $schema
	):string
	{
		$pp_ary = [
			'role_short'	=> 'g',
			'system'		=> $this->systems->get_system($schema),
		];

		return $this->link->link_no_attr('users', $pp_ary,
			['id' => $id], $this->get_str($id, $schema));
	}
}
