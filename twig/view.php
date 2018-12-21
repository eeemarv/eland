<?php

namespace twig;

use service\view as service_view;

class view
{
	private $view;

	public function __construct(service_view $view)
	{
		$this->view = $view;
	}

	public function get(array $param, string $entity = null):array
	{
		return $this->view->merge($param, $entity);
	}
}
