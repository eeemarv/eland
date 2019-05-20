<?php

namespace render;

use render\link as render_link;
use render\tag as render_tag;
use service\assets;

class btn_nav
{
	const ORDER_AND_GROUP = [
		'csv'				=> false,
		'columns_show'		=> false,
		'view'				=> true,
		'nav'				=> true,
	];

	protected $render_link;
	protected $tag;
	protected $assets;
	protected $out = [];

	public function __construct(
		render_link $link,
		render_tag $tag,
		assets $assets
	)
	{
		$this->link = $link;
		$this->tag = $tag;
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
					$this->out[$key] . '</span>';
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
			return $this->tag->get('button', [
					'class' 	=> 'btn btn-default',
					'title'		=> $title,
					'disabled'	=> 'disabled',
				],
				$this->tag->fa($fa)
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
	)
	{

		return $this->link->link_fa_only($route, $params_context,
			$params, [
				'class'	=> 'btn btn-default' . ($active ? ' active' : ''),
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
		return $this->link->link_fa_only($route, $params_context,
			$params, [
				'class'	=> 'btn btn-default',
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

		$this->out['csv'] = $this->tag->get('a', [
				'class'	=> 'csv btn btn-info',
				'title'	=> 'Download CSV',
			],
			$this->tag->fa('file')
		);
	}

	public function columns_show():void
	{
		$this->out['columns_show'] = $this->tag->get('button', [
				'class'			=> 'btn btn-default',
				'title'			=> 'Weergave kolommen',
				'data-toggle'	=> 'collapse',
				'data-target'	=> '#columns_show',
			],
			$this->tag->fa('columns')
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
