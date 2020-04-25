<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\SystemsService;
use Twig\Extension\RuntimeExtensionInterface;

class BaseUrlRuntime implements RuntimeExtensionInterface
{
	protected SystemsService $systems_service;
	protected string $env_app_scheme;

	public function __construct(
		SystemsService $systems_service,
		string $env_app_scheme
	)
	{
		$this->systems_service = $systems_service;
		$this->env_app_scheme = $env_app_scheme;
	}

	public function get(string $schema)
	{
		return $this->env_app_scheme . $this->systems_service->get_host($schema);
	}

	public function get_link_open(string $schema)
	{
		$out = '<a href="';
		$out .= $this->get($schema);
		$out .= '">';

		return $out;
	}
}
