<?php declare(strict_types=1);

namespace App\Render;

use App\Render\LinkRender;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;

class AccountRender
{
	protected LinkRender $link_render;
	protected SystemsService $systems_service;
	protected UserCacheService $user_cache_service;
	protected VarRouteService $vr;

	public function __construct(
		LinkRender $link_render,
		SystemsService $systems_service,
		UserCacheService $user_cache_service,
		VarRouteService $vr
	)
	{
		$this->link_render = $link_render;
		$this->systems_service = $systems_service;
		$this->user_cache_service = $user_cache_service;
		$this->vr = $vr;
	}

	public function get_str(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);

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

		return $this->link_render->link_no_attr($this->vr->get('users_show'), $pp_ary,
			['id' => $id], $this->get_str($id, $schema));
	}

	public function link_url(
		int $id,
		array $pp_ary
	):string
	{
		$schema = $this->systems_service->get_schema($pp_ary['system']);

		return $this->link_render->link_url($this->vr->get('users_show'), $pp_ary,
			['id' => $id], $this->get_str($id, $schema), []);
	}

	public function inter_link(
		int $id,
		string $schema,
		SessionUserService $su
	):string
	{
		$pp_ary = [
			'system'	=> $this->systems_service->get_system($schema),
		];

		if ($su->schema() === $schema)
		{
			$pp_ary['role_short'] = $su->role_short();
		}
		else
		{
			$pp_ary['role_short'] = 'g';
			$pp_ary['os'] = $su->system();
		}

		return $this->link_render->link_no_attr('users_show', $pp_ary,
			['id' => $id], $this->get_str($id, $schema));
	}
}
