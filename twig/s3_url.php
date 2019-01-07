<?php

namespace twig;

class s3_url
{
	protected $s3_url;

	public function __construct(
		string $s3_url
	)
	{
		$this->s3_url = $s3_url;
	}

	public function get(
		string $file
	):string
	{
		return $this->s3_url . $file;
	}

	public function get_link_open(
		string $file
	):string
	{
		$out = '<a href="';
		$out .= $this->get($file);
		$out .= '">';

		return $out;
	}
}
