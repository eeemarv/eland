<?php

namespace render;

class select
{
	public function get_options(
		array $option_ary,
		string $selected
	):string
	{
		$out = '';

		foreach ($option_ary as $value => $label)
		{
			$out .= '<option value="' . $value . '"';
			$out .= $value == $selected ? ' selected="selected"' : '';
			$out .= '>' . htmlspecialchars($value, ENT_QUOTES) . '</option>';
		}

		return $out;
	}
}
