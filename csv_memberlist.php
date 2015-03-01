<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."inc_memberlist.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

$prefix_filterby = $_GET["prefix_filterby"];

if(isset($s_id)){
	$user_date = date("Y-m-d");
	show_ptitle($user_date);
	$userrows = get_all_active_users($user_orderby,$prefix_filterby);
 	show_all_users($userrows);
}else{
	redirect_login($rootpath);
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle($user_date){
        header("Content-disposition: attachment; filename=marva-contact-".$user_date .".csv");
        header("Content-Type: application/force-download");
        header("Content-Transfer-Encoding: binary");
        header("Pragma: no-cache");
        header("Expires: 0");
}

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


?>

