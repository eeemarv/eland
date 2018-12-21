<?php

namespace twig;

use service\user_simple_cache;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;

class web_user
{
	private $user_simple_cache;
	private $schema;
	private $local;

	private $format = [];

	public function __construct(
		user_simple_cache $user_simple_cache,
		RequestStack $request_stack,
		UrlGenerator $url_generator
	)
	{
		$this->user_simple_cache = $user_simple_cache;
		$request = $request_stack->getCurrentRequest();
		$this->schema = $request->attributes->get('schema');
		$this->access = $request->attributes->get('access');
		$this->url_generator = $url_generator;	
	}

	public function get(int $id):string
	{
		if (!isset($this->local[$this->schema]))
		{
			$this->local[$this->schema] = $this->user_simple_cache->get($this->schema);
		}

		if (!isset($this->local[$this->schema][$id]))
		{
			return '';
		}

		$out = '<a href="';
		$out .= $this->url_generator->generate('user_show', [
			'user'		=> $id,
			'access'	=> $this->access,
			'schema'	=> $this->schema,
			'user_type'	=> $this->local[$this->schema][$id][0],
		]);
		$out .= '">';
		$out .= $this->local[$this->schema][$id][1];
		$out .= '</a>';

		return $out;
	}
}
