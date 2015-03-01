<?php
ob_start();
$rootpath = "../";

// set the config array otherwise we'll get a loop
//$nocheckconfig=TRUE;
//require_once($rootpath."includes/inc_config.php");

// get the initial includes
//require_once($rootpath."includes/inc_default.php");
//require_once($rootpath."includes/inc_adoconnection.php");

session_start(); 
global $_SESSION;
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];
	
//include($rootpath."includes/inc_header.php");
#include($rootpath."includes/inc_nav.php");

show_ptitle();
show_body();

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>eLAS niet ingesteld</h1>";
}

function show_body(){
	echo "Je eLAS installatie is niet ingesteld voor deze site.<br>";
	echo "Lees het INSTALL document dat in het archief bestand zat voor informatie over de installatie.<br>";
	echo "Bij problemen meld je dit best aan de ontwikkelaar(s) via de elas website op ";
	echo "<a href='http://elas.vsbnet.be'>elas.vsbnet.be</a>";
}

//include($rootpath."includes/inc_sidebar.php");
//include($rootpath."includes/inc_footer.php");
?>
