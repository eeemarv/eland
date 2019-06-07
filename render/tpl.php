<?php

use Symfony\Component\HttpFoundation\Response;

use cnst\pages as cnst_pages;

// not used (yet)

class tpl
{
	protected $assets;

	protected $header;
	protected $c_ary;
	protected $footer;

	public function __construct(
		assets $assets,
		config $config

	)
	{
		$this->assets = $assets;
		$this->config = $config;

	}

	public function add(string $in):void
	{
	}

	public function get():string
	{
		return '';
	}

	public function get_response():Response
	{
		return new Response($this->get());
	}
}
