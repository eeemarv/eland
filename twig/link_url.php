<?php

namespace twig;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class link_url
{
	protected $url_generator;

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

	public function context_url_open(
		string $route,
		array $params_context,
		array $params
	):string
	{
        return '<a href="' . $this->url_generator->generate(
			$route, array_merge($params, $params_context),
			UrlGeneratorInterface::ABSOLUTE_URL) . '">';
	}
}
