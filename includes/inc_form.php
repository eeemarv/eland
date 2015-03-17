<?php

function render_select_options($option_ary, $selected)
{
	foreach ($option_ary as $key => $value)
	{
		echo '<option value="' . $key . '"';
		echo ($key == $selected) ? ' selected="selected"' : '';
		echo '>' . htmlspecialchars($value, ENT_QUOTES) . '</option>';
	}
}
