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
	$contacts = get_all_contacts();
 
	show_all_contacts($contacts);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_addlink(){
	echo "<div class='border_b'>| <a href='add.php'>Contact toevoegen</a> |</div>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Overzicht contacten</h1>";
}

function show_all_contacts($contacten){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='header'>";
	echo "<td nowrap> <strong>Type</strong></td>";
	echo "<td nowrap><strong>Gebruiker</strong></td>";
	echo "<td nowrap><strong>Waarde</strong></td>";
	echo "<td nowrap><strong>Commentaar</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($contacten as $value){
		$rownumb=$rownumb+1;
			if($rownumb % 2 == 1){
				echo "<tr class='uneven_row'>";
			}else{
	        	echo "<tr class='even_row'>";
			}
		echo "<td nowrap valign='top'>".htmlspecialchars($value["abbrev"],ENT_QUOTES)."</td>";
		echo "<td nowrap valign='top'>".htmlspecialchars($value["uname"],ENT_QUOTES)."</td>";
		
		echo "<td valign='top' nowrap><a href='view.php?id=".$value["cid"]."'>";
		echo htmlspecialchars($value["value"],ENT_QUOTES);
		echo "</a></td>";
		echo "<td valign='top'>".htmlspecialchars($value["ccomments"],ENT_QUOTES)."</td>";
		echo "</tr>";
		
	}
	echo "</table></div>";
}

function get_all_contacts(){
	global $db;
	$query = "SELECT *, ";
	$query .= "contact.id AS cid, ";
	$query .= " users.name AS uname, ";
	$query .= " contact.comments AS ccomments, ";
	$query .= " type_contact.name AS tcname ";
	$query .= " FROM contact, users, type_contact ";
	$query .= " WHERE contact.id_user = users.id ";
	$query .= " AND contact.id_type_contact = type_contact.id";
	$contacts = $db->GetArray($query);
	return $contacts;
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
