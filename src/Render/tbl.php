<?php declare(strict_types=1);

namespace App\Render;

class tbl
{
	protected $out = [];

	public function td(
		string $content
	):void
	{
		$this->out[] = $content;
	}

	public function tde(
		string $content
	):void
	{
		$this->out[] = $content;
	}
}
