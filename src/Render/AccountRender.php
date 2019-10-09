<?php declare(strict_types=1);

namespace App\Render;

use App\Render\LinkRender;
use App\Service\SystemsService;
use App\Service\UserCacheService;

class AccountRender
{
	protected $link_render;
	protected $systems_service;
	protected $user_cache_service;
	protected $r_users_show;

	public function __construct(
		LinkRender $link_render,
		SystemsService $systems_service,
		UserCacheService $user_cache_service,
		string $r_users_show
	)
	{
		$this->link_render = $link_render;
		$this->systems_service = $systems_service;
		$this->user_cache_service = $user_cache_service;
		$this->r_users_show = $r_users_show;
	}

	public function get_str(int $id, string $schema):string
	{
		$user = $this->user_cache->get($id, $schema);

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
		$schema = $this->systems_service->get_schema($pp_ary['system']);

		return $this->link_render->link_no_attr($this->r_users_show, $pp_ary,
			['id' => $id], $this->get_str($id, $schema));
	}

	public function link_url(
		int $id,
		array $pp_ary
	):string
	{
		$schema = $this->systems_service->get_schema($pp_ary['system']);

		return $this->link_render->link_url($this->r_users_show, $pp_ary,
			['id' => $id], $this->get_str($id, $schema), []);
	}

	public function inter_link(
		int $id,
		string $schema
	):string
	{
		$pp_ary = [
			'role_short'	=> 'g',
			'system'		=> $this->systems_service->get_system($schema),
		];

		return $this->link_render->link_no_attr('users_show', $pp_ary,
			['id' => $id], $this->get_str($id, $schema));
	}
}
