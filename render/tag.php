<?php

namespace render;

class tag
{
	public function get(
		string $tag,
		array $attr,
		string $content
	):string
	{
		$out = '<';
		$out .= $tag;

		foreach ($attr as $name => $val)
		{
			$out .= ' ' . $name . '="' . $val . '"';
		}

		$out .= '>';
		$out .= $content;
		$out .= '</' . $tag . '>';
		return $out;
	}

	public function fa(string $fa):string
	{
		return '<i class="fa fa-' . $fa . '"></i>';
	}
}