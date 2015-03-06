<?php

$r = "\r\n";

$php_sapi_name = php_sapi_name();


header('Content-Type:text/plain');

echo '*** Cron eLAS-Heroku ***' . $r . $r;
echo 'version: ' . exec('git describe') . $r;
echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r . $r;
echo 'a.letsa.net: ' . getenv('ELAS_DOMAIN_SESSION_A__LETSA__NET');
echo 'b.letsa.net: ' . getenv('ELAS_DOMAIN_SESSION_B__LETSA__NET');
