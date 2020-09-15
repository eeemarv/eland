<?php declare(strict_types=1);

namespace App\Render;

use App\Cnst\MenuCnst;
use App\Render\LinkRender;
use App\Render\TagRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\PageParamsService;

class BtnNavRender
{
	const ORDER_AND_GROUP = [
		'admin'				=> true,
		'columns_show'		=> false,
		'view'				=> true,
		'nav'				=> true,
	];

	protected LinkRender $link_render;
	protected ConfigService $config_service;
	protected TagRender $tag_render;
	protected AssetsService $assets_service;
	protected array $out = [];
	protected array $local_admin = [];

	public function __construct(
		LinkRender $link_render,
		ConfigService $config_service,
		TagRender $tag_render,
		AssetsService $assets_service
	)
	{
		$this->link_render = $link_render;
		$this->config_service = $config_service;
		$this->tag_render = $tag_render;
		$this->assets_service = $assets_service;
	}

	public function get():string
	{
		$out = [];

		if ($this->local_admin)
		{
			$local_admin = '<div class="btn-group" role="group">';
			$local_admin .= '<button type="button" class="btn btn-info btn-lg dropdown-toggle" ';
			$local_admin .= 'data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
			$local_admin .= '<i class="fa fa-cog" title="Admin functies"></i>&nbsp;';
			$local_admin .= '<span class="caret"></span>';
			$local_admin .= '</button>';
			$local_admin .= '<div class="dropdown-menu dropdown-menu-right">';
			$local_admin .= implode('', $this->local_admin);
			$local_admin .= '</div>';
			$local_admin .= '</div>';

			$this->out['admin'][] = $local_admin;
		}

		foreach (self::ORDER_AND_GROUP as $key => $is_group)
		{
			if (!isset($this->out[$key]))
			{
				continue;
			}

			if ($is_group)
			{
				$out[] = '<span class="btn-group" role="group">' .
					implode('', $this->out[$key]) . '</span>';
				continue;
			}

			$out[] = $this->out[$key];
		}

		return implode('&nbsp;', $out);
	}

	public function has_content():bool
	{
		return count($this->out) > 0;
	}

	public function btn_fa_disable(
		string $route,
		array $params_context,
		array $params,
		string $title,
		string $fa
	):string
	{
		if (count($params) < 1)
		{
			return $this->tag_render->get('button', [
					'class' 	=> 'btn btn-default btn-lg border border-secondary-li',
					'title'		=> $title,
					'disabled'	=> 'disabled',
				],
				$this->tag_render->fa($fa)
			);
		}

		return $this->btn_fa($route, $params_context,
			$params, $title, $fa);
	}

	public function btn_fa_active(
		string $route,
		array $params_context,
		array $params,
		string $title,
		string $fa,
		bool $active
	):string
	{
		return $this->link_render->link_fa_only($route, $params_context,
			$params, [
				'class'	=> 'btn btn-default btn-lg border border-secondary-li' . ($active ? ' active' : ''),
				'title'	=> $title,
			],
			$fa);
	}

	public function btn_fa(
		string $route,
		array $params_context,
		array $params,
		string $title,
		string $fa
	):string
	{
		return $this->link_render->link_fa_only($route, $params_context,
			$params, [
				'class'	=> 'btn btn-default btn-lg border border-secondary-li',
				'title'	=> $title,
			],
			$fa);
	}

	public function nav(
		string $route,
		array $params_context,
		array $params_prev,
		array $params_next,
		bool $order_reversed
	):void
	{
		$params_up = $order_reversed ? $params_next : $params_prev;
		$params_down = $order_reversed ? $params_prev : $params_next;
		$title_up = $order_reversed ? 'Volgende' : 'Vorige';
		$title_down = $order_reversed ? 'Vorige' : 'Volgende';

		$this->out['nav'][] = $this->btn_fa_disable(
			$route, $params_context, $params_up,
			$title_up, 'chevron-up');

		$this->out['nav'][] = $this->btn_fa_disable(
			$route, $params_context, $params_down,
			$title_down, 'chevron-down');
	}

	public function nav_list(
		string $route,
		array $params_context,
		array $params_list,
		string $title,
		string $fa
	):void
	{
		$this->out['nav'][] = $this->btn_fa(
			$route, $params_context, $params_list,
			$title, $fa);
	}

	public function csv():void
	{
		$this->assets_service->add(['csv.js']);

		$this->out['admin'][] = $this->tag_render->get('a', [
				'class'		=> 'btn btn-info btn-lg text-light',
				'data-csv'	=> '',
				'title'		=> 'Download CSV',
			],
			$this->tag_render->fa('file')
		);
	}

	public function local_admin(
		string $menu,
		PageParamsService $pp
	):void
	{
		$main_menu = MenuCnst::LOCAL_ADMIN_MAIN[$menu] ?? $menu;

		if (!isset(MenuCnst::LOCAL_ADMIN[$main_menu]))
		{
			return;
		}

		foreach(MenuCnst::LOCAL_ADMIN[$main_menu] as $menu_key => $ary)
		{
			if (isset($ary['config_en']))
			{
				if ($ary['config_en'] === 'intersystem')
				{
					if (!$this->config_service->get_intersystem_en($pp->schema()))
					{
						continue;
					}
				}

				if (!$this->config_service->get_bool($ary['config_en'], $pp->schema()))
				{
					continue;
				}
			}

			if (isset($ary['divider']))
			{
				$this->local_admin[] = '<li class="divider"></li>';
				continue;
			}

			$class = 'dropdown-item';
			$class .= $menu === $menu_key ? ' active' : '';

			$this->local_admin[] = $this->link_render->link_fa(
				$ary['route'], $pp->ary(), $ary['params'] ?? [],
				$ary['label'], ['class' => $class],
				$ary['fa']);
		}
	}

	public function columns_show():void
	{
		$this->out['columns_show'] = $this->tag_render->get('button', [
				'class'			=> 'btn btn-default btn-lg border border-secondary-li',
				'title'			=> 'Weergave kolommen',
				'data-toggle'	=> 'collapse',
				'data-target'	=> '#columns_show',
			],
			$this->tag_render->fa('columns')
		);
	}

	public function view(
		string $route,
		array $params_context,
		array $params,
		string $title,
		string $fa,
		bool $active
	):void
	{
		$this->out['view'][] = $this->btn_fa_active(
			$route, $params_context, $params,
			$title, $fa, $active);
	}
}
