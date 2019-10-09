<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\SystemsService;

class base_url
{
	protected $systems_service;
	protected $protocol;

	public function __construct(
		SystemsService $systems_service,
		string $protocol
	)
	{
		$this->systems_service = $systems_service;
		$this->protocol = $protocol;
	}

	public function get(string $schema)
	{
		return $this->protocol . $this->systems_service->get_host($schema);
	}

	public function get_link_open(string $schema)
	{
		$out = '<a href="';
		$out .= $this->get($schema);
		$out .= '">';

		return $out;
	}
}
