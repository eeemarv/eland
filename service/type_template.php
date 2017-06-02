<?php

namespace service;

use service\config;

class type_template
{
	private $config;
	private $type;

	private $names = [
		'code'			=> ['rekeningcode', 'LETScode'],
		'codes'			=> ['rekeningcodes', 'LETScodes'],
	];

	public function __construct(config $config)
	{
		$this->config = $config;

		$this->type = $this->config->get('template_lets') ? 1 : 0;
	}

	public function get($key)
	{
		return $this->names[$key][$this->type];
	}

	public function get_cap($key)
	{
		return ucfirst($this->names[$key][$this->type]);
	}
}
