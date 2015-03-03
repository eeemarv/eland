<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_passwords.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

if ($s_accountrole == "admin"){
	$posted_list = array();
	$mysetting = $_POST["setting"];
	$posted_list["value"] = $_POST["value"];
	//$errorlist = validate_input($posted_list);
			
	if (!empty($errorlist)){
		echo "<font color='red'><strong>Fout: ";
		foreach($errorlist as $key => $value){
			echo $value;
			echo " | ";
		}
		echo "</strong></font>";
	} else {
		if(writeconfig($mysetting, $posted_list["value"]) == true){
			echo "<font color='green'><strong>OK</font> - Instelling opgeslagen</strong>";
		} else {
			echo "<font color='red'><strong>Fout bij het opslaan</strong></font>";
		}
	}
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function validate_input($posted_list){
	$errorlist = array();
	if (empty($posted_list["value"])){
		$errorlist["value"] = "Geef een waarde in";
	}

	return $errorlist;
}
