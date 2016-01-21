<?php


echo '</div>';

//echo '<div class="clearfix"></div>';
//echo '<div class="container-fluid">';
echo '<footer class="footer">';

echo '<p><a href="http://letsa.net">eLAND';
echo '</a>&nbsp; web app voor letsgroepen</p>';

echo '<p><b>Rapporteer bugs in de <a href="https://github.com/eeemarv/eland/issues">Github issue tracker</a>.';
echo '</b>';
echo ' (Maak eerst een <a href="https://github.com">Github</a> account aan.)</p>';

echo '</footer>';
//echo '</div>';
//echo '</div>';
//echo '</div>';

echo '<script src="' . $cdn_jquery . '"></script>';
echo '<script src="' . $cdn_bootstrap_js . '"></script>';
echo '<script src="' . $cdn_footable_js . '"></script>';
echo '<script src="' . $cdn_footable_sort_js . '"></script>';
echo '<script src="' . $cdn_footable_filter_js . '"></script>';

echo '<script src="' . $rootpath . 'js/base.js"></script>';

if (isset($includejs))
{
	echo $includejs;
}

echo '</body>';
echo '</html>';

