<?php declare(strict_types=1);

namespace App\Render;

use App\Render\LinkRender;
use App\Render\TagRender;
use App\Service\Assets;

class BtnNavRender
{
	const ORDER_AND_GROUP = [
		'csv'				=> false,
		'columns_show'		=> false,
		'view'				=> true,
		'nav'				=> true,
	];

	protected $link_render;
	protected $tag_render;
	protected $assets;
	protected $out = [];

	public function __construct(
		LinkRender $link_render,
		TagRender $tag_render,
		Assets $assets
	)
	{
		$this->link_render = $link_render;
		$this->tag_render = $tag_render;
		$this->assets = $assets;
	}

	public function get():string
	{
		$out = [];

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
					'class' 	=> 'btn btn-default btn-lg',
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
				'class'	=> 'btn btn-default btn-lg' . ($active ? ' active' : ''),
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
				'class'	=> 'btn btn-default btn-lg',
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
		$this->assets->add(['csv.js']);

		$this->out['csv'] = $this->tag_render->get('a', [
				'class'	=> 'csv btn btn-info btn-lg',
				'title'	=> 'Download CSV',
			],
			$this->tag_render->fa('file')
		);
	}

	public function columns_show():void
	{
		$this->out['columns_show'] = $this->tag_render->get('button', [
				'class'			=> 'btn btn-default btn-lg',
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