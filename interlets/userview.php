<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id)){
	if($s_accountrole == 'user' || $s_accountrole == 'admin'){
		show_grouptitle();
		show_interletsgroups();
	} else {
		redirect_login($rootpath);
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_grouptitle(){
	echo "<h1>Andere (interlets) groepen raadplegen</h1>";
}

function show_interletsgroups(){
	global $db;
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1'>";
	$query = "SELECT * FROM letsgroups WHERE apimethod <> 'internal'";
	$letsgroups = $db->Execute($query);
	foreach($letsgroups as $key => $value){
		echo "<tr><td nowrap>";
		//a href='#' onclick=window.open('$myurl','addgroup','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Groep toevoegen</a>
		//echo "<a href='" .$value["url"] ."'>" .$value["groupname"] ."</a>";a
		echo "<a href='#' onclick=window.open('redirect.php?letsgroup=" .$value["id"] ."','interlets','location=no,menubar=no,scrollbars=yes')>" .$value["groupname"] ."</a>";
		echo "</td></tr>";
	}

	echo "</table>";

}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
