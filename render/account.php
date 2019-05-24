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

	public function get_str_from_ary(array $user_ary):string
	{
		if (!count($user_ary))
		{
			return '** (leeg) **';
		}

		if (!$user_ary['letscode'])
		{
			if (!$user_ary['name'])
			{
				return '** (gn code & naam) **';
			}

			return '** (gn code) ** ' . $user_ary['name'];
		}

		if (!$user_ary['name'])
		{
			return $user_ary['letscode'] . ' ** (gn naam) **';
		}

		return $user_ary['letscode'] . ' ' . $user_ary['name'];
	}

	public function get_str_from_id(
		int $id,
		string $schema
	):string
	{
		if ($id === 0)
		{
			return '** (id: 0) **';
		}

		$user_ary = $this->user_cache->get($id, $schema);

		return $this->get_str_from_ary($user_ary);
	}

	public function link_field(
		int $id,
		array $pp_ary,
		string $field
	):string
	{
		$user = $this->user_cache->get($id,
			$this->systems->get_schema_from_system($pp_ary['system']));

		return $this->link->link_no_attr('users', $pp_ary,
			['id' => $id], $user[$field]);
	}

	public function link(int $id, array $pp_ary):string
	{

	}

	public function link_code(int $id, array $pp_ary):string
	{
		$user = $this->user_cache->get($id,
			$this->systems->get_schema_from_system($pp_ary['system']));

	}
}
