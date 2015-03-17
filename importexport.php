<?php
ob_start();
$rootpath = "./";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!(isset($s_id) && ($s_accountrole == "admin"))){
 	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_header.php");
echo "<h1>Import/Export</h1>";

echo "<p><a href='export/export_users.php'>Export Gebruikers</a>";
echo "<br><a href='export/export_contacts.php'>Export Contactgegevens</a>";
echo "<br><a href='export/export_categories.php'>Export Categories</a> [Kunnen niet geimporteerd worden!]";
echo "<br><a href='export/export_messages.php'>Export Vraag/Aanbod</a> [Vereist gelijke categorie ID's]";
echo "<br><a href='export/export_transactions.php'>Export Transacties</a></p>";
echo "<hr>";
//echo "<p><a href='import/import_csv.php'>CSV bestand importeren</a>"; not working correctly

include($rootpath."includes/inc_footer.php");
