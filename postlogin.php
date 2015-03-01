<?php

// PROCESS THE LOGIN CREDENTIALS AND BUILD THE SESSION

ob_start();
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_hosting.php");
require_once($rootpath."includes/inc_auth.php");

// Check the hosting contract from json if hosted
if($configuration["hosting"]["enabled"] == 1){
	$contract = get_contract();
	$enddate = strtotime($contract["end"]);
	$graceperiod = $contract["graceperiod"];
	$now = time();

	switch($enddate){
		case (($enddate + ($graceperiod * 24 * 60 * 60)) < $now):
			//Het contract is vervallen en uit grace
			//LOCK eLAS
			$locked = 1;
			log_event("","System", "eLAS is locked, logging in is disabled");
			break;
		case (($enddate + ($graceperiod * 24 * 60 * 60)) > $now):
			//Het contract is niet vervallen of uit grace
			//extra unLOCK eLAS
			$locked = 0;
			break;
	}
} else {
	$locked = 0;
}
//debug
//print_r($_POST);
//session_start();
//$_SESSION["id"] = $row["id"];
//$_SESSION["name"] = $row["name"];
//$_SESSION["letscode"] = $row["letscode"];
//$_SESSION["accountrole"] = $row["accountrole"];

$myuser = get_user_maildetails_by_login($_POST["login"]);

if ($xmlconfig->hosting == 1 && $_POST["login"] == "master" && hash('sha512', $_POST["password"]) == $provider->masterpassword) {
	log_event(0,"Master","Login as master user");
	startmastersession();
	$status = "OK - Gebruiker ingelogd";
} else {
	if ((!empty($_POST["password"]) && !empty($_POST['login'])) && ($myuser["password"] == hash('sha512', $_POST["password"]) || $myuser["password"] == md5($_POST["password"]) || $myuser["password"] == sha1($_POST["password"]))){
		if($myuser["status"] == 0){
			$status = "Gebruiker is gedeactiveerd";
		} else {
			if(readconfigfromdb("maintenance") == 1 && $myuser["accountrole"] != "admin"){
				$status = "eLAS is in onderhoud, probeer later opnieuw";
			} else {
				if($locked == 1){
					$status = "Hostingcontract vervallen, logins zijn uitgeschakeld";
				} else { 
					startsession($myuser);
					$status = "OK - Gebruiker ingelogd";
				}
			}
		}
	} else {
		$status = "Login gefaald";
		$uname = $_POST["login"];
		log_event($s_id,"LogFail","Login for user $uname with password failed");
	}
}

// Return the status to the calling page
echo $status;

?>
