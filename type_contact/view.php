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
	if (isset($_GET["id"])){
		$id = $_GET["id"];
		$contacttype = get_contacttype($id);
		show_ptitle();
		show_contacttype($contacttype);
	}else{
		redirect_overview();
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

function show_ptitle(){
	echo "<h1>Contacttype bekijken</h1>";
}

function show_contacttype($contacttype){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>Naam</strong></td>";
	echo "<td valign='top'><strong>Afkorting</strong></td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($contacttype["name"],ENT_QUOTES);
	echo "</td>";
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($contacttype["abbrev"],ENT_QUOTES);
	echo "</td>";
	echo "</tr>";
	echo "</table></div>";

	echo "<div class='border_b'>";
	echo "| <a href='edit.php?id=".$contacttype["id"]."'>Aanpassen</a> | ";
	echo "<a href='delete.php?id=".$contacttype["id"]."'>Verwijderen</a> |";
	echo "</div>";
}

function get_contacttype($id){
	global $db;
	$query = "SELECT * FROM type_contact ";
	$query .= "WHERE id=".$id;
	$contacttype = $db->GetRow($query);
	return $contacttype;
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
