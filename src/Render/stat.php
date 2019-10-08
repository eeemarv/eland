<?php declare(strict_types=1);

namespace App\Render;

class stat
{
	protected $content = [];

	public function before(string $key, string $str):self
	{
		$this->content[$key]['before'] = $str;
		return $this;
	}

	public function after(string $key, string $str):self
	{
		$this->content[$key]['after'] = $str;
		return $this;
	}

	public function add(string $key, string $str):self
	{
		$this->content[$key]['content'][] = $str;
		return $this;
	}

	public function get(string $key):string
	{
		if (!isset($this->content[$key]))
		{
			return '';
		}

		$out = $this->content[$key]['before'] ?? '';

		if (isset($this->content[$key]['content']))
		{
			foreach ($this->content[$key]['content'] as $str)
			{
				$out .= $str;
			}
		}

		$out .= $this->content[$key]['after'] ?? '';

		return $out;
	}
}
