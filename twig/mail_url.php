<?php

namespace twig;

use service\systems;

class mail_url
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

	public function get(
		string $route,
		array $params,
		string $schema
	):string
	{
		$out = $this->protocol . $this->systems->get_host($schema);
		$out .= '/' . $route . '.php';

		if (count($params))
		{
			$out .= '?';
			$out .= http_build_query($params);
		}

		return $out;
	}

	public function get_link_open(
		string $route,
		array $params,
		string $schema
	):string
	{
		$out = '<a href="';
		$out .= $this->get($route, $params, $schema);
		$out .= '">';

		return $out;
	}
}
