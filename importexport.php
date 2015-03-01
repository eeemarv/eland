<?php
ob_start();
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	show_listing();
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
 	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Import/Export</h1>";
}

function show_listing(){
	echo "<p><a href='export/export_users.php'>Export Gebruikers</a>";
	echo "<br><a href='export/export_contacts.php'>Export Contactgegevens</a>";
	echo "<br><a href='export/export_categories.php'>Export Categories</a> [Kunnen niet geimporteerd worden!]";
	echo "<br><a href='export/export_messages.php'>Export Vraag/Aanbod</a> [Vereist gelijke categorie ID's]";
	echo "<br><a href='export/export_transactions.php'>Export Transacties</a></p>";
	echo "<hr>";
        echo "<p><a href='import/import_csv.php'>CSV bestand importeren</a>";
}
	
include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
