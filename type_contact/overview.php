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

if(isset($s_id) && ($s_accountrole == "admin")){
	show_addlink();
	show_ptitle();
	$contacttypes = get_all_contacttypes();
 	show_all_contacttypes($contacttypes);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_addlink(){
	echo "<div class='border_b'>| <a href='add.php'>Contacttype toevoegen</a> |</div>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Overzicht contacttypes</h1>";
}

function show_all_contacttypes($contacttypes){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td><strong>Naam </strong></td>";
	echo "<td><strong>Afkorting</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($contacttypes as $value){
	 	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}

		echo "<td valign='top'>";
		echo "<a href='view.php?id=".$value["id"]."'>";
		echo htmlspecialchars($value["name"],ENT_QUOTES);
		echo "</a>";
		echo "</td><td>";
		if(!empty($value["abbrev"])){
			echo htmlspecialchars($value["abbrev"],ENT_QUOTES);
		}
		echo "</td></tr>";
	}
	echo "</table></div>";
}

function get_all_contacttypes(){
	global $db;
	$query = "SELECT * FROM type_contact";
	$contacttypes = $db->GetArray($query);
	return $contacttypes;
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
