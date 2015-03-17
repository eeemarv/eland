<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($s_id)){
	header('Location: ' . $rootpath . 'login.php');
}

$contact = get_contact();
show_contact($contact);

///////////////////////////

function get_contact(){
	global $s_id;
	global $db;
	$query = "SELECT *, ";
	$query .= " contact.id AS cid, users.id AS uid, type_contact.id AS tcid, ";
	$query .= " type_contact.name AS tcname, users.name AS uname ";
	$query .= " FROM users, type_contact, contact ";
	$query .= " WHERE users.id=".$s_id;
	$query .= " AND contact.id_type_contact = type_contact.id ";
	$query .= " AND users.id = contact.id_user ";
	$contact = $db->GetArray($query);
	return $contact;
}

function show_contact($contact){
	echo "<table cellpadding='0' cellspacing='0' border='1' width='99%' class='data'>";

	echo "<tr class='even_row'><td colspan='5'><p><strong>Contactinfo</strong></p></td></tr>";
		echo "<tr>";
		echo "<th valign='top'>Type</th>";
	echo "<th valign='top'>Waarde</th>";
	echo "<th valign='top'>Commentaar</th>";
	echo "<th valign='top'>Publiek</th>";
	echo "<th valign='top'></th>";
	echo "</tr>";

	foreach($contact as $key => $value){
		echo "<tr valign='top'  nowrap><td>".htmlspecialchars($value["abbrev"],ENT_QUOTES)." </td>";
		echo "<td valign='top' nowrap>".htmlspecialchars($value["value"],ENT_QUOTES)."</td>";
		echo "<td valign='top'>".htmlspecialchars($value["comments"],ENT_QUOTES)."</td>";
		echo "<td valign='top'>";
		if(trim($value["flag_public"]) == 't'){
			echo "Ja";
		}else{
			echo "Nee";
		}

		echo "</td>";
		echo "<td valign='top' nowrap>|<a href='mydetails_cont_edit.php?id=".$value["id"]."'>";
		echo " aanpassen </a> |";
		echo "<a href='mydetails_cont_delete.php?id=".$value["id"]."'> verwijderen </a>|</td>";
		echo "</tr>";
	}
	echo "</table>";
}
