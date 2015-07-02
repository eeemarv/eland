<?php

function startsession($user){
	global $tr;
	session_start();
	$_SESSION["id"] = $user["id"];
	$_SESSION["name"] = $user["name"];
	$_SESSION["fullname"] = $user["fullname"];
	$_SESSION["login"] = $user["login"];
	$_SESSION["user_postcode"] = $user["postcode"];
	$_SESSION["letscode"] = $user["letscode"];
	$_SESSION["accountrole"] = $user["accountrole"];
	$_SESSION["userstatus"] = $user["status"];
	$_SESSION["email"] = $user["emailaddress"];
	$_SESSION["lang"] = $user["lang"];
	$_SESSION["status"] = array();
	$_SESSION["type"] = "local";

	// Check if the user has a keypair, generatie if missing.
	if(empty($user["privkey"]) || empty($user["privkey"])){
		genkeys($user["id"]);
	}

	$browser = $_SERVER['HTTP_USER_AGENT'];
	log_event($user["id"],"Login","User " .$user["login"] ." logged in");
	log_event($user["id"],"Agent","$browser");
	insert_date_into_lastlogin($user["id"]);
}

function startmastersession() {

	session_start();
	$_SESSION["id"] = 0;
	$_SESSION["name"] = "master";
	$_SESSION["fullname"] = "eLAS Master";
	$_SESSION["login"] = "master";
	$_SESSION["user_postcode"] = "0000";
	$_SESSION["letscode"] = "000000";
	$_SESSION["accountrole"] = "admin";
	$_SESSION["userstatus"] = 1;
	$_SESSION["email"] = "";
	$_SESSION["lang"] = "nl";
	$_SESSION["status"] = array();
	$_SESSION["type"] = "master";
	//var_dump($_SESSION);
	log_event(0,"Login","Master user " .$user["login"] ." logged in");
}

function insert_date_into_lastlogin($s_id)
{
        global $db;
        $posted_list["lastlogin"] = date("Y-m-d H:i:s");
        $result = $db->AutoExecute("users", $posted_list, 'UPDATE', "id=$s_id");

}

function genkeys($id) {
	global $db;

	$Key = openssl_pkey_new(array(
		'private_key_bits' => 1024,
		'private_key_type' => OPENSSL_KEYTYPE_RSA,
	));
	openssl_pkey_export($Key, $privateKey);
	$keyDetails = openssl_pkey_get_details($Key);
	//echo $privateKey;
	//echo $keyDetails['key'];

	$error = 0;
	$query = "UPDATE users SET privkey = '" .$privateKey ."' WHERE id = " .$id;
	if(!$db->execute($query)){
		$error = 1;
	}
	$query = "UPDATE users SET pubkey = '" .$keyDetails['key'] ."' WHERE id = " .$id;
	if(!$db->execute($query)){
		$error = 1;
	}

	if($error != 0){
		setstatus("Sleutelgeneratie gefaald");
	} else {
		setstatus("Sleutelpaar aangemaakt");
	}
}

function rest_auth () {
		global $_SESSION;
		return true;
}

?>
