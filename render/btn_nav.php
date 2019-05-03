<?php

namespace render;

use render\link as render_link;

class btn_nav
{
	protected $render_link;
	protected $out = '';

	public function __construct(
		render_link $render_link
	)
	{
		$this->render_link = $render_link;
	}

	public function get():string
	{
		if ($this->out === '')
		{
			return '';
		}

		$this->out = '<span class="btn-group" role="group">' . $this->out;

		return $this->out . '</span>';
	}

	public function has_content():bool
	{
		return $this->out !== '';
	}

	private function btn_simple_fa(string $link, string $title, string $fa):void
	{
		$ret = ' class="btn btn-default" title="';
		$ret .= $title;
		$ret .= '"><i class="fa fa-chevron-';
		$ret .= $fa;
		$ret .= '"></i>';

		if ($link === '')
		{
			$this->out .= '<button disabled="disabled"' . $ret . '</button>';
		}

		$this->out .= '<a href="' . $link . '"' . $ret . '</a>';
	}

	private function link_params_only(
		string $route,
		array $context_params,
		array $params
	):string
	{
		if (count($params) > 0)
		{
			return $this->render_link->context_path(
				$route, $context_params, $params);
		}

		return '';
	}

	public function next_down(
		string $route,
		array $context_params,
		array $params
	):void
	{
		$this->btn_simple_fa(
			$this->link_params_only($route, $context_params, $params),
			'Volgende', 'down');
	}

	public function next_up(
		string $route,
		array $context_params,
		array $params
	):void
	{
		$this->btn_simple_fa(
			$this->link_params_only($route, $context_params, $params),
			'Volgende', 'up');
	}

	public function prev_down(
		string $route,
		array $context_params,
		array $params
	):void
	{
		$this->btn_simple_fa(
			$this->link_params_only($route, $context_params, $params),
			'Vorige', 'down');
	}

	public function prev_up(
		string $route,
		array $context_params,
		array $params
	):void
	{
		$this->btn_simple_fa(
			$this->link_params_only($route, $context_params, $params),
			'Vorige', 'up');
	}

	public function btn(
		string $route,
		array $context_params,
		array $params,
		string $title,
		string $fa
	):void
	{
		$this->btn_simple_fa(
			$this->link_params_only($route, $context_params, $params),
			$title, $fa);
	}
}
