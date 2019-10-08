<?php declare(strict_types=1);

namespace App\Twig;

use service\systems;

class base_url
{
	protected $systems;
	protected $protocol;

	public function __construct(
		systems $systems,
		string $protocol
	)
	{
		$this->systems = $systems;
		$this->protocol = $protocol;
	}

	public function get(string $schema)
	{
		return $this->protocol . $this->systems->get_host($schema);
	}

	public function get_link_open(string $schema)
	{
		$out = '<a href="';
		$out .= $this->get($schema);
		$out .= '">';

		return $out;
	}
}
