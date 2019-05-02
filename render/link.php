<?php

namespace render;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class link
{
	protected $url_generator;

	public function __construct(
		UrlGeneratorInterface $url_generator
	)
	{
		$this->url_generator = $url_generator;
	}

    public function path(string $route, array $parameters = []):string
    {
        return $this['url_generator']->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

	function link(
		string $route,
		array $context_params,
		array $params,
		string $label,
		array $attr,
		string $fa = '',
		bool $collapse = false
	):string
	{
		$out = '<a href="';
		$out .= $this->path($route, array_merge($params, $context_params));
		$out .= '"';

		foreach ($attr as $name => $val)
		{
			$out .= ' ' . $name . '="' . $val . '"';
		}

		$out .= '>';
		$out .= $fa === '' ? '' : '<i class="fa fa-' . $fa .'"></i>';
		$out .= $collapse ? '<span class="hidden-xs hidden-sm"> ' : ' ';
		$out .= htmlspecialchars($label, ENT_QUOTES);
		$out .= $collapse ? '</span>' : '';
		$out .= '</a>';
		return $out;
	}

	function btn_cancel(string $route, array $context_params, array $params):string
	{
		return $this->link($route, $context_params, $params,
			'Annuleren', ['class'	=> 'btn btn-default'],
			'undo');
	}

	function btn_top_del(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Verwijderen'):string
	{
		return $this->link($route, $context_params, $params,
			'Verwijderen', [
				'class' => 'btn btn-danger',
				'title'	=> $title,
			], 'times', true);
	}

	function btn_top_add(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Toevoegen'):string
	{
		return $this->link($route, $context_params, $params,
			'Toevoegen', [
				'class'	=> 'btn btn-success',
				'title'	=> $title,
			], 'plus', true);
	}

	function btn_top_edit(
		string $route,
		array $context_params,
		array $params,
		string $title = 'Aanpassen'):string
	{
		return $this->link($route, $context_params, $params,
			'Aanpassen', [
				'class'	=> 'btn btn-primary',
				'title'	=> $title,
			], 'pencil', true);
	}

}
