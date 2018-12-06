<?php

namespace service;

use service\groups;

class this_group
{
	protected $groups;
	protected $schema;
	protected $host;

	public function __construct(groups $groups)
	{
		$this->groups = $groups;

		$this->host = $_SERVER['SERVER_NAME'] ?? '';
		$this->schema = $this->host ? $this->groups->get_schema($this->host) : '';
	}

	public function get_schema():string
	{
		return $this->schema ?? '';
	}

	public function get_host():string
	{
		return $this->host ?? '';
	}
}
