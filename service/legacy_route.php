<?php

namespace service;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class legacy_route
{
	protected $app;

	public function __construct(
		app $app
	)
	{
		$this->app = $app;
	}

	public function render(string $name):Response
	{
		$app = $this->app;

		ob_start();
		require_once __DIR__ . '/../plain/' . $name . '.php';
		return new Response(ob_get_clean());
	}

	public function typeahead(string $name):Response
	{
		$app = $this->app;

		ob_start();
		require_once __DIR__ . '/../typeahead/' . $name . '.php';
		return new Response(ob_get_clean());
	}

	public function ajax(string $name):Response
	{
		$app = $this->app;

		ob_start();
		require_once __DIR__ . '/../ajax/' . $name . '.php';
		return new Response(ob_get_clean());
	}
}
