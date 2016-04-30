<?php

$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$h1 = 'Rapporten';
$fa = 'calculator';

include $rootpath . 'includes/inc_header.php';

echo '<p><a href="transperuser.php">Transacties per gebruiker/datum</a></p>';

include $rootpath . 'includes/inc_footer.php';

