<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

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
	echo "<h1>Rapporten</h1>";
}

function show_listing(){
	echo "<p><a href='transperuser.php'>Transacties per gebruiker/datum</a>";
	echo "<br><a href='balance.php'>Saldo op datum</a>";
	echo "<br><a href='messages.php'>Lijst Vraag & Aanbod per categorie</a>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
