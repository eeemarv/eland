<?php
ob_start();
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_passwords.php");
require_once($rootpath."includes/inc_mailfunctions.php");

//debug
//print_r($_POST);
//session_start();
//$_SESSION["id"] = $row["id"];
//$_SESSION["name"] = $row["name"];
//$_SESSION["letscode"] = $row["letscode"];
//$_SESSION["accountrole"] = $row["accountrole"];

if(empty($_POST["email"])){
	echo "Geef een mailadres op";
	log_event($s_id,"System","Empty activation request");
} else {
	log_event($s_id,"System","Activation request for " .$_POST["email"]);
	// Search for the mailaddress in the contact table
	global $db;
	$query = "SELECT * FROM contact WHERE value = '" .$_POST["email"] ."'";
	$contact = $db->GetRow($query);

	if(!empty($contact["value"])){
		$user = get_user_maildetails($contact["id_user"]);
		$posted_list["pw1"] = generatePassword();
		if(update_password($contact["id_user"], $posted_list) == TRUE){
			sendactivationmail($posted_list["pw1"], $user,0);
			log_event($s_id,"System","Account " .$user["login"] ." reactivated");
		} else {
			echo "Heractivatie mislukt, contacteer de beheerder";
			log_event($s_id,"System","Account " .$user["login"] ." activation failed");
		}
	} else {
		log_event($s_id,"System","Activation request for unkown mail " .$_POST["email"]);
		echo "E-mail adress " .$_POST["email"] ." niet gevonden";
	}
}

?>
