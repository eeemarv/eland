<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PageParamsService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

class LinkExtension
{
	public function __construct(
		private readonly UrlGeneratorInterface $url_generator,
		private readonly PageParamsService $pp
	)
	{
	}

  #[AsTwigFunction(name: 'link')]
  public function link(
		string $route,
		array $params
	):string
  {
    return $this->url_generator->generate(
			$route, [
        ...$this->pp->ary(),
        ...$params,
      ], UrlGeneratorInterface::ABSOLUTE_PATH,
    );
	}

  #[AsTwigFilter(name: 'link')]
	public function link_filter(
		string $label,
		string $route,
		array $params = [],
		array $attr = []
	)
	{
    $out = '<a href="';
		$out .= $this->url_generator->generate(
			$route, [
        ...$this->pp->ary(),
        ...$params,
      ], UrlGeneratorInterface::ABSOLUTE_PATH,
    );
		$out .= '"';

		foreach ($attr as $name => $value)
		{
			$out .= ' ';
			$out .= $name;
			$out .= '="';
			$out .= $value;
			$out .= '"';
		}

		$out .= '>';
		$out .= htmlspecialchars($label, ENT_QUOTES);
		$out .= '</a>';

		return $out;
	}
}
