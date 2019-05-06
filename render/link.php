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

    public function path(
		string $route,
		array $params
	):string
    {
        return $this->url_generator->generate(
			$route, $params, UrlGeneratorInterface::ABSOLUTE_PATH);
	}

	public function context_path(
		string $route,
		array $params_context,
		array $params
	):string
	{
        return $this['url_generator']->generate(
			$route, array_merge($params, $params_context),
			UrlGeneratorInterface::ABSOLUTE_PATH);
	}

	public function link(
		string $route,
		array $params_context,
		array $params,
		string $label,
		array $attr,
		string $fa = '',
		bool $collapse = false
	):string
	{
		$out = '<a href="';
		$out .= $this->context_path($route, $params_context, $params);
		$out .= '"';

		foreach ($attr as $name => $val)
		{
			$out .= ' ' . $name . '="' . $val . '"';
		}

		$out .= '>';
		$out .= $fa === '' ? '' : '<i class="fa fa-' . $fa .'"></i>';
		$out .= $collapse ? '<span class="hidden-xs hidden-sm">' : '';
		$out .= $label === '' ? '' : htmlspecialchars($label, ENT_QUOTES);
		$out .= $collapse ? '</span>' : '';
		$out .= '</a>';
		return $out;
	}

	public function link_fa(
		string $route,
		array $params_context,
		array $params,
		array $attr,
		string $fa
	):string
	{
		$out = '<a href="';
		$out .= $this->context_path($route, $params_context, $params);
		$out .= '"';

		foreach ($attr as $name => $val)
		{
			$out .= ' ' . $name . '="' . $val . '"';
		}

		$out .= '>';
		$out .= '<i class="fa fa-' . $fa .'"></i>';
		$out .= '</a>';
		return $out;
	}

	public function btn_cancel(
		string $route,
		array $params_context,
		array $params
	):string
	{
		return $this->link($route, $params_context, $params,
			'Annuleren', ['class'	=> 'btn btn-default'],
			'undo');
	}
}
