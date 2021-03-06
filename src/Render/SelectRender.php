<?php declare(strict_types=1);

namespace App\Render;

class SelectRender
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
			$out .= '>' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
		}

		return $out;
	}
}
