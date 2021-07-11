<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PageParamsService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class LinkRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected UrlGeneratorInterface $url_generator,
		protected PageParamsService $pp
	)
	{
	}

    public function link(
		string $route,
		array $params
	):string
    {
        return $this->url_generator->generate(
			$route, array_merge($this->pp->ary(), $params), UrlGeneratorInterface::ABSOLUTE_PATH);
	}

	public function link_filter(
		string $label,
		string $route,
		array $params = [],
		array $attr = []
	)
	{
        $out = '<a href="';
		$out .= $this->url_generator->generate(
			$route, array_merge($this->pp->ary(), $params), UrlGeneratorInterface::ABSOLUTE_PATH);
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
