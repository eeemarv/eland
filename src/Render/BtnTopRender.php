<?php declare(strict_types=1);

namespace App\Render;

use App\Render\LinkRender;

class BtnTopRender
{
	protected LinkRender $link_render;
	protected array $out = [];

	public function __construct(
		LinkRender $link_render
	)
	{
		$this->link_render = $link_render;
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
		$this->out[] = $this->link_render->link_fa_collapse($route,
			$context_params, $params,
			'Verwijderen', [
				'class' => 'btn btn-danger btn-lg',
				'title'	=> $title,
			], 'times');
	}

	public function add(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Toevoegen'):void
	{
		$this->out[] = $this->link_render->link_fa_collapse($route,
			$context_params, $params,
			'Toevoegen', [
				'class'	=> 'btn btn-success btn-lg',
				'title'	=> $title,
			], 'plus');
	}

	public function edit(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Aanpassen'):void
	{
		$this->out[] = $this->link_render->link_fa_collapse($route,
			$context_params, $params,
			'Aanpassen', [
				'class'	=> 'btn btn-primary btn-lg',
				'title'	=> $title,
			], 'pencil');
	}

	public function approve(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Goedkeuren'):void
	{
		$this->out[] = $this->link_render->link_fa_collapse($route,
			$context_params, $params,
			'Goedkeuren', [
				'class'	=> 'btn btn-warning btn-lg',
				'title'	=> $title,
			], 'check');
	}

	public function add_trans(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Transactie'):void
	{
		$this->out[] = $this->link_render->link_fa_collapse($route,
			$context_params, $params,
			'Transactie', [
				'class'	=> 'btn btn-warning btn-lg',
				'title'	=> $title,
			], 'exchange');
	}

	public function edit_pw(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Paswoord'):void
	{
		$this->out[] = $this->link_render->link_fa_collapse($route,
			$context_params, $params,
			'Paswoord', [
				'class'	=> 'btn btn-info btn-lg',
				'title'	=> $title,
			], 'key');
	}

	public function create(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Toevoegen'):void
	{
		$this->out[] = $this->link_render->link_fa_collapse($route,
			$context_params, $params,
			'Aanmaken', [
				'class'	=> 'btn btn-success btn-lg',
				'title'	=> $title,
			], 'plus');
	}

	public function config(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Configuratie'):void
	{
		$this->out[] = $this->link_render->link_fa_collapse($route,
			$context_params, $params,
			'Configuratie', [
				'class'	=> 'btn btn-info btn-lg',
				'title'	=> $title,
			], 'cog');
	}

	public function local(string $link, string $title, string $fa):void
	{
		$out = '<a href="';
		$out .= $link;
		$out .= '" class="btn btn-info btn-lg" title="';
		$out .= $title;
		$out .= '"><i class="fa fa-';
		$out .= $fa;
		$out .= '"></i><span class="hidden-xs hidden-sm">&nbsp;';
		$out .= $title;
		$out .= '</span></a>';
		$this->out[] = $out;
	}
}
