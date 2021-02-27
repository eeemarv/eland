<?php declare(strict_types=1);

namespace App\Render;

use App\Render\LinkRender;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\UserCacheService;

class AccountRender
{
	public function __construct(
		protected LinkRender $link_render,
		protected SystemsService $systems_service,
		protected UserCacheService $user_cache_service
	)
	{
	}

	public function get_str(?int $id, string $schema):string
	{
		if (!isset($id) || !$id)
		{
			return '*** (leeg) ***';
		}

		$user = $this->user_cache_service->get($id, $schema);

		$code = $user['code'] ?? '';
		$name = $user['name'] ?? '';

		$str = trim($code . ' ' . $name);

		return $str === '' ? '** (leeg) ***' : $str;
	}

	public function str(
		?int $id,
		string $schema
	):string
	{
		if (!isset($id) || !$id)
		{
			return '** (leeg) **';
		}

		return $this->get_str($id, $schema);
	}

	public function str_id(
		?int $id,
		string $schema
	):string
	{
		if (!isset($id) || !$id)
		{
			return '** (leeg) **';
		}

		return $this->str($id, $schema) . ' (' . $id . ')';
	}

	public function link(
		?int $id,
		array $pp_ary
	):string
	{
		if (!isset($id) || !$id)
		{
			return '*** leeg ***';
		}

		$schema = $this->systems_service->get_schema($pp_ary['system']);

		return $this->link_render->link_no_attr('users_show', $pp_ary,
			['id' => $id], $this->get_str($id, $schema));
	}

	public function link_url(
		int $id,
		array $pp_ary
	):string
	{
		$schema = $this->systems_service->get_schema($pp_ary['system']);

		return $this->link_render->link_url('users_show', $pp_ary,
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
