<?php declare(strict_types=1);

namespace App\Twig;

use Deprecated;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

class LinkUrlExtension
{
	public function __construct(
		private readonly UrlGeneratorInterface $url_generator
	)
	{
	}

	#[\Deprecated]
  #[AsTwigFunction(name: 'context_url')]
	public function context_url(
		string $route,
		array $params_context,
		array $params
	):string
	{
    return $this->url_generator->generate(
			$route, [...$params, ...$params_context],
			UrlGeneratorInterface::ABSOLUTE_URL);
	}

	#[\Deprecated]
  #[AsTwigFunction(name: 'context_url_open')]
	public function context_url_open(
		string $route,
		array $params_context,
		array $params
	):string
	{
    return '<a href="' . $this->url_generator->generate(
			$route, [...$params, ...$params_context],
			UrlGeneratorInterface::ABSOLUTE_URL) . '">';
	}

  #[AsTwigFunction(name: 'a_open')]
	public function a_open(
		string $route,
		array $params = []
	):string
	{
    return '<a href="' . $this->url_generator->generate(
			$route, $params,
			UrlGeneratorInterface::ABSOLUTE_URL) . '">';
	}
}
