<?php declare(strict_types=1);

namespace App\Render;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LinkRender
{
	protected UrlGeneratorInterface $url_generator;

	public function __construct(
		UrlGeneratorInterface $url_generator
	)
	{
		$this->url_generator = $url_generator;
	}

    public function url(
		string $route,
		array $params
	):string
    {
        return $this->url_generator->generate(
			$route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
	}

	public function context_url(
		string $route,
		array $params_context,
		array $params
	):string
	{
        return $this->url_generator->generate(
			$route, array_merge($params, $params_context),
			UrlGeneratorInterface::ABSOLUTE_URL);
	}

	public function link_url(
		string $route,
		array $params_context,
		array $params,
		string $label,
		array $attr
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
		$out .= htmlspecialchars($label, ENT_QUOTES);
		$out .= '</a>';

		return $out;
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
        return $this->url_generator->generate(
			$route, array_merge($params, $params_context),
			UrlGeneratorInterface::ABSOLUTE_PATH);
	}

	public function redirect(
		string $route,
		array $params_context,
		array $params
	):void
	{
		$redirect = $this->context_path($route, $params_context, $params);
		header('Location: ' . $redirect);
		exit;
	}

	public function link(
		string $route,
		array $params_context,
		array $params,
		string $label,
		array $attr
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
		$out .= htmlspecialchars($label, ENT_QUOTES);
		$out .= '</a>';

		return $out;
	}

	public function link_no_attr(
		string $route,
		array $params_context,
		array $params,
		?string $label
	):string
	{
		$out = '<a href="';
		$out .= $this->context_path($route, $params_context, $params);
		$out .= '">';

		if (isset($label) && $label !== '')
		{
			$out .= htmlspecialchars($label, ENT_QUOTES);
		}
		else
		{
			$out .= '*** leeg ***';
		}

		$out .= '</a>';

		return $out;
	}

	public function link_fa(
		string $route,
		array $params_context,
		array $params,
		string $label,
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
		$out .= '<i class="fa fa-' . $fa .'"></i>&nbsp;';
		$out .= htmlspecialchars($label, ENT_QUOTES);
		$out .= '</a>';

		return $out;
	}

	public function link_fa_collapse(
		string $route,
		array $params_context,
		array $params,
		string $label,
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
		$out .= '<span class="hidden-xs hidden-sm">&nbsp;';
		$out .= htmlspecialchars($label, ENT_QUOTES);
		$out .= '</span>';
		$out .= '</a>';

		return $out;
	}

	public function link_fa_only(
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
			'Annuleren', ['class'	=> 'btn btn-default btn-lg'],
			'undo');
	}
}
