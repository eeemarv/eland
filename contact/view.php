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

		$contact = get_contact($id);
		show_ptitle();
		show_contact($contact);

	}else{
		redirect_overview();
	}
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
	echo "<h1>Contact bekijken</h1>";
}

function show_contact($contact){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top' nowrap><strong>Type</strong></td>";
	echo "<td valign='top' nowrap><strong>Gebruiker</strong></td>";
	echo "<td valign='top' nowrap><strong>Waarde</strong></td>";
	echo "<td valign='top' nowrap><strong>Commentaar</strong></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($contact["tcname"],ENT_QUOTES);
	echo "</td>";
	echo  "<td valign='top' nowrap>";
	echo htmlspecialchars($contact["uname"],ENT_QUOTES)." (".trim($contact["letscode"]).")";
	echo "</td>";
	echo  "<td valign='top' nowrap>";
	echo htmlspecialchars($contact["value"],ENT_QUOTES);
	echo "</td>";
	echo  "<td valign='top'>";
	echo nl2br(htmlspecialchars($contact["ccomments"],ENT_QUOTES));
	echo "</td>";
	echo "</tr>";
	echo "</table></div>";

	echo "<div class='border_b'>";
	echo "| <a href='edit.php?id=".$contact["cid"]."'>Aanpassen</a> |";
	echo "<a href='delete.php?id=".$contact["cid"]."'> Verwijderen</a> |";
	echo "</div>";
}

function get_contact($id){
	global $db;
	$query = "SELECT *, ";
	$query .= "contact.id AS cid, ";
	$query .= "contact.comments AS ccomments, ";
	$query .= "type_contact.name AS tcname, ";
	$query .= "users.name AS uname ";
	$query .= "FROM contact, users, type_contact ";
	$query .= "WHERE contact.id =".$id;
	$query .= " AND contact.id_user = users.id ";
	$query .= " AND contact.id_type_contact = type_contact.id ";
	$contact = $db->GetRow($query);
	return $contact;
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
