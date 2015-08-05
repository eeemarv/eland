<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$h1 = 'Rapporten';
$fa = 'calculator';

include $rootpath . 'includes/inc_header.php';

echo '<p><a href="transperuser.php">Transacties per gebruiker/datum</a></p>';
echo '<p><a href="balance.php">Saldo op datum</a></p>';
echo '<p><a href="messages.php">Lijst Vraag & Aanbod per categorie</a></p>';

include $rootpath . 'includes/inc_footer.php';
