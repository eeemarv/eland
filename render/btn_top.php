<?php

namespace render;

use render\link;

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
}
