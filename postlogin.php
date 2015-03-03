<?php

// PROCESS THE LOGIN CREDENTIALS AND BUILD THE SESSION

ob_start();
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_auth.php");

$locked = 0;

//debug
//print_r($_POST);
//session_start();
//$_SESSION["id"] = $row["id"];
//$_SESSION["name"] = $row["name"];
//$_SESSION["letscode"] = $row["letscode"];
//$_SESSION["accountrole"] = $row["accountrole"];

$myuser = get_user_maildetails_by_login($_POST["login"]);

$master_password = getenv('ELAS_MASTER_PASSWORD');

if ($master_password && hash('sha512', $_POST["password"]) == $master_password) {
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
