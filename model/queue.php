<?php

namespace model;

use model\job;

abstract class queue extends job
{
	protected $next = 0;

	public function __construct()
	{
		$this->next = $this->get_interval() + time();
	}

	public function get_next()
	{
		return $this->next;
	}

	public function set_next(int $next = 0)
	{
		$this->next = $next ? $next : time() + $this->get_interval();
		return $this;
	}
}
