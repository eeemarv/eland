<?php

echo '</div>';
echo '</div>';
echo '</div>';

echo '<footer class="footer">';

echo '<p><a href="http://letsa.net">eLAND</a> web app voor gemeenschapsmunten</p>';

echo '<p><b>Rapporteer bugs in de <a href="https://github.com/eeemarv/eland/issues">Github issue tracker</a>.';
echo '</b>';
echo ' (Maak eerst een <a href="https://github.com">Github</a> account aan.)</p>';

echo '</footer>';

echo $app['assets']->render_js();

echo '</body>';
echo '</html>';

