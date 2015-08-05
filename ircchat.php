<?php
ob_start();
$rootpath = '';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$h1 = 'LETS Chat';
$fa = 'comments-o';

include $rootpath . 'includes/inc_header.php';

$tag = readconfigfromdb('systemtag');
$name = strtolower(preg_replace('/\s+/', '', $s_login));
$name = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
$nick = $name ."_" . $tag;

echo '<div class="embed-responsive embed-responsive-16by9">';
echo '<iframe class="embed-responsive-item" src="http://webchat.freenode.net?nick=' .$nick . '&channels=letsbe\">';
echo '</iframe>';
echo '</div>';

echo "<p><small><i>";
echo "Deze chat laat je toe om met LETSers over heel Vlaanderen te chatten via het Freenode IRC netwerk<br>Je kan hier ook via een client op inloggen met de server irc.freenode.net, kanaal #letsbe<br>";
echo "Om misbruik op het netwerk te voorkomen vraagt het Freenode netwerk je om de CAPTCHA over te typen om spammers buiten te houden";
echo "<br>Sluit gewoon dit venster om uit te loggen op de chatroom.";
echo "</i></small></p>";

include $rootpath . 'includes/inc_footer.php';
