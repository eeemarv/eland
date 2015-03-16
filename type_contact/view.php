<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!isset($s_id) && ($s_accountrole == "admin")){
	header('Location: ' . $rootpath . 'login.php');
	exit;
}

if (!isset($_GET["id"])){
	header("Location: overview.php");
	exit;
}

$id = $_GET["id"];
$contacttype = get_contacttype($id);

include($rootpath."includes/inc_header.php");
echo "<h1>Contacttype bekijken</h1>";
show_contacttype($contacttype);
include($rootpath."includes/inc_footer.php");


///////////////////////

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
	if (in_array($contacttype['abbrev'], array('mail', 'tel', 'gsm', 'adr')))
	{
		echo '<p>Beschermd contact type: kan niet aangepast worden.</p>';
	}
	else
	{
		echo "| <a href='edit.php?id=".$contacttype["id"]."'>Aanpassen</a> | ";
		echo "<a href='delete.php?id=".$contacttype["id"]."'>Verwijderen</a> |";

	}
	echo "</div>";
}

function get_contacttype($id){
	global $db;
	$query = "SELECT * FROM type_contact ";
	$query .= "WHERE id=".$id;
	$contacttype = $db->GetRow($query);
	return $contacttype;
}
