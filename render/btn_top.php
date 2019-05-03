<?php

namespace render;

use render\link as render_link;

class btn_top
{
	protected $render_link;
	protected $out = [];

	public function __construct(
		render_link $render_link
	)
	{
		$this->render_link = $render_link;
	}

	public function btn_cancel(string $route, array $context_params, array $params):string
	{
		return $this->render_link->link($route, $context_params, $params,
			'Annuleren', ['class'	=> 'btn btn-default'],
			'undo');
	}

	public function get():string
	{
		return implode('', $this->out);
	}

	public function has_content():bool
	{
		return count($this->out) > 0;
	}

	public function del(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Verwijderen'):void
	{
		$this->out[] = $this->render_link->link($route, $context_params, $params,
			'Verwijderen', [
				'class' => 'btn btn-danger',
				'title'	=> $title,
			], 'times', true);
	}

	public function add(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Toevoegen'):void
	{
		$this->out[] = $this->render_link->link($route, $context_params, $params,
			'Toevoegen', [
				'class'	=> 'btn btn-success',
				'title'	=> $title,
			], 'plus', true);
	}

	public function edit(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Aanpassen'):void
	{
		$this->out[] = $this->render_link->link($route, $context_params, $params,
			'Aanpassen', [
				'class'	=> 'btn btn-primary',
				'title'	=> $title,
			], 'pencil', true);
	}

	public function approve(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Goedkeuren'):void
	{
		$this->out[] = $this->render_link->link($route, $context_params, $params,
			'Goedkeuren', [
				'class'	=> 'btn btn-warning',
				'title'	=> $title,
			], 'check', true);
	}

	public function add_trans(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Transactie'):void
	{
		$this->out[] = $this->render_link->link($route, $context_params, $params,
			'Transactie', [
				'class'	=> 'btn btn-warning',
				'title'	=> $title,
			], 'exchange', true);
	}

	public function edit_pw(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Paswoord'):void
	{
		$this->out[] = $this->render_link->link($route, $context_params, $params,
			'Paswoord', [
				'class'	=> 'btn btn-info',
				'title'	=> $title,
			], 'key', true);
	}

	public function local(string $link, string $title, string $fa):void
	{
		$out = '<a href="';
		$out .= $link;
		$out .= '" class="btn btn-info" title="';
		$out .= $title;
		$out .= '"><i class="fa fa-';
		$out .= $fa;
		$out .= '"></i><span class="hidden-xs hidden-sm">&nbsp;';
		$out .= $title;
		$out .= '</span></a>';
		$this->out[] = $out;
	}
}
