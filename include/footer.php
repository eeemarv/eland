<?php

echo '</div>';
echo '</div>';
echo '</div>';

echo '<footer class="footer">';

echo '<p><a href="https://eland.letsa.net">';
echo 'eLAND</a> web app voor gemeenschapsmunten</p>';

echo '<p><b>Rapporteer bugs in de ';
echo '<a href="https://github.com/eeemarv/eland/issues">';
echo 'Github issue tracker</a>.';
echo '</b>';
echo ' (Maak eerst een <a href="https://github.com">';
echo 'Github</a> account aan.)</p>';

echo '</footer>';

echo '</div>';

echo $app['assets']->get_js();

echo '</body>';
echo '</html>';
