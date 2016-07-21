<?php

echo '</div>';

echo '<footer class="footer">';

echo '<p><a href="http://letsa.net">eLAND';
echo '</a>&nbsp; web app voor letsgroepen</p>';

echo '<p><b>Rapporteer bugs in de <a href="https://github.com/eeemarv/eland/issues">Github issue tracker</a>.';
echo '</b>';
echo ' (Maak eerst een <a href="https://github.com">Github</a> account aan.)</p>';

echo '</footer>';

foreach ($include_js as $js)
{
	echo '<script src="' . $js . '"></script>';
}

echo '</body>';
echo '</html>';

