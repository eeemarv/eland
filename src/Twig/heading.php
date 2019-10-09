<?php declare(strict_types=1);

namespace App\Twig;

use App\Render\heading as render_heading;

class heading
{
	protected $render_heading;

	public function __construct(render_heading $render_heading)
	{
		$this->render_heading = $render_heading;
	}

	public function get_h1():string
	{
		return $this->render_heading->get_h1();
	}

	public function get_sub():string
	{
		return $this->render_heading->get_sub();
	}
}
