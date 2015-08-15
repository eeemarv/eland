<?php
ob_start();
$rootpath = "";
$role = 'user';
require_once($rootpath."includes/inc_default.php");

$prefix_filterby = $_GET["prefix_filterby"];

$q = 'SELECT id, letscode, fullname, postcode, saldo
	FROM users
	WHERE status IN (1, 2, 3, 4)
		AND accountrole <> \'guest\'';
$q .= ($prefix_filterby <> 'ALL') ? ' AND users.letscode like \'' . $prefix_filterby . '%\'' : '';
$userrows = $db->GetArray($q);

$user_date = date("Y-m-d");

header("Content-disposition: attachment; filename=marva-contact-".$user_date .".csv");
header("Content-Type: application/force-download");
header("Content-Transfer-Encoding: binary");
header("Pragma: no-cache");
header("Expires: 0");

show_all_users($userrows);

function get_contacts($userid){
	global $db;
	$query = "SELECT * FROM contact ";
	$query .= " WHERE id_user =".$userid;
	$contactrows = $db->GetArray($query);
	return $contactrows;
}

function get_contact($userid,$contact_type_id){
        global $db;
        $query = "SELECT * FROM contact WHERE id_user = " .$userid;
	$query .= " AND id_type_contact = " .$contact_type_id;
	//$query .= " AND contact.flag_public = 1";
        $contact = $db->GetRow($query);
        return $contact;
}

function get_contact_types(){
	global $db;
        $query = "SELECT * FROM type_contact";
	$query .= " ORDER BY id";
	$contact_types = $db->GetArray($query);
        return $contact_types;
}

function show_all_users($userrows){
	global $s_accountrole;
	echo "\"Code\",\"Naam\",\"Postcode\",\"Saldo\"";
	$contact_types = get_contact_types();
	$numcontacttypes = 0;
	foreach($contact_types as $ctkey => $ctvalue) {
		$numcontacttypes = $numcontacttypes + 1;
		echo ",\"";
		echo $ctvalue["name"];
		echo "\"";
	}
	echo "\n";
	foreach($userrows as $key => $value){
		echo "\"" .$value["letscode"] ."\"";
		echo ",\"" .$value["fullname"] ."\"";
		echo ",\"" .$value["postcode"] ."\"";
		echo "," .$value["saldo"];

		// Foreach contacttype, look up if the user has a contact (of this type)
		foreach($contact_types as $ctkey => $ctvalue) {
			 $contact = get_contact($value["id"],$ctvalue["id"]);
			 echo ",\"";
			 if($contact["flag_public"] == 1 || $s_accountrole == "admin"){
			 	echo $contact["value"];
			 }
			 echo "\"";

		}
		echo "\n";
	}

}
