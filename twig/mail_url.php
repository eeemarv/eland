<?php

namespace twig;

use service\groups;

class mail_url
{
	protected $groups;
	protected $protocol;

	public function __construct(
		groups $groups,
		string $protocol
	)
	{
		$this->groups = $groups;
		$this->protocol = $protocol;
	}

	public function get(
		string $route,
		array $params,
		string $schema
	):string
	{
		$out = $this->protocol . $this->groups->get_host($schema);
		$out .= '/' . $route . '.php';

		if (count($params))
		{
			$out .= '?';
			$out .= http_build_query($params);
		}

		return $out;
	}
}
