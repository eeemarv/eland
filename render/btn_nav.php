<?php

namespace render;

use render\link as render_link;
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
	protected $assets;
	protected $out = [];

	public function __construct(
		render_link $render_link,
		assets $assets
	)
	{
		$this->render_link = $render_link;
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

	public function btn_link_fa(
		string $route,
		array $params_context,
		array $params,
		string $title,
		string $fa
	):string
	{
//		$this->render_link->link($route, $params_context, $context, '')



		$ret = ' class="btn btn-default" title="';
		$ret .= $title;
		$ret .= '"><i class="fa fa-chevron-';
		$ret .= $fa;
		$ret .= '"></i>';

		if (count($params) < 1)
		{
			return '<button disabled="disabled"' . $ret . '</button>';
		}

		$out = '<a href="';
		$out .= $this->render_link->context_path($route, $params_context, $params);
		$out .= '"' . $ret . '</a>';

		return $out;
	}

	public function nav(
		string $route,
		array $params_context,
		array $params_prev,
		array $params_next,
		array $params_list,
		string $fa_list,
		bool $order_reversed
	):void
	{
		$params_up = $order_reversed ? $params_next : $params_prev;
		$params_down = $order_reversed ? $params_prev : $params_next;
		$title_up = $order_reversed ? 'Volgende' : 'Vorige';
		$title_down = $order_reversed ? 'Vorige' : 'Volgende';

		$this->out['nav'][] = $this->btn_link_fa(
			$route, $params_context, $params_up,
			$title_up, 'up');

		$this->out['nav'][] = $this->btn_link_fa(
			$route, $params_context, $params_down,
			$title_down, 'down');

		$this->out['nav'][] = $this->btn_link_fa(
			$route, $params_context, $params_list,
			'Lijst', $fa_list);
	}

	public function list(
		string $route,
		array $params_context,
		array $params_list,
		string $fa_list
	):void
	{
		$this->out['nav'][] = $this->btn_link_fa(
			$route, $params_context, $params_list,
			'Lijst', $fa_list);
	}

	public function csv():void
	{
		$this->assets->add(['csv.js']);

		$out = '<a href="#" class="csv btn btn-info btn-md" ';
		$out .= 'title="Download CSV">';
		$out .= '<i class="fa fa-file"></i>';
		$out .= '</a>&nbsp;';
		$this->out['csv'] = $out;
	}

	public function columns_show():void
	{
		$out = '<button class="btn btn-default" title="Weergave kolommen" ';
		$out .= 'data-toggle="collapse" data-target="#columns_show"';
		$out .= '><i class="fa fa-columns"></i></button>';
		$this->out['columns_show'] = $out;
	}

	public function view(
		string $route,
		array $params_context,
		array $params,
		string $view_name,
		string $title,
		string $fa
	):void
	{
		$this->out['view'][] = $this->btn_simple_fa(
			$this->link_params_only($route, $params_context, $params),
			$title, $fa);
	}

	public function btn(
		string $route,
		array $params_context,
		array $params,
		string $title,
		string $fa
	):string
	{
		return $this->btn_simple_fa(
			$this->link_params_only($route, $params_context, $params),
			$title, $fa);
	}

	private function btn__fa(
		string $link,
		string $title,
		string $fa
	):string
	{
		$ret = ' class="btn btn-default" title="';
		$ret .= $title;
		$ret .= '"><i class="fa fa-chevron-';
		$ret .= $fa;
		$ret .= '"></i>';

		if ($link === '')
		{
			return '<button disabled="disabled"' . $ret . '</button>';
		}

		return '<a href="' . $link . '"' . $ret . '</a>';
	}
}
