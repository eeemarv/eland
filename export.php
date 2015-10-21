<?php

$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$h1 = 'Export';
$fa = 'download';

include $rootpath . 'includes/inc_header.php';

echo '<p><a href="' . $rootpath . 'export/export_users.php">Export Gebruikers</a>';
echo '<br><a href="' . $rootpath . 'export/export_contacts.php">Export Contactgegevens</a>';
echo '<br><a href="' . $rootpath . 'export/export_categories.php">Export Categories</a>';
echo '<br><a href="' . $rootpath . 'export/export_messages.php">Export Vraag/Aanbod</a> [Vereist gelijke categorie ID\'s]';
echo '<br><a href="' . $rootpath . 'export/export_transactions.php">Export Transacties</a></p>';

include $rootpath . 'includes/inc_footer.php';
