<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Config;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ConfigExtension extends AbstractExtension
{
	private $config;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	public function getFunctions()
    {
        return [
			new TwigFunction('config', [$this, 'get']),
        ];
    }

	public function get(string $key, string $schema):string
	{
		/*
		if (!isset($schema))
		{
			$schema = $context['schema'];
		}

		*/

		return $this->config->get($key, $schema);
	}
}
