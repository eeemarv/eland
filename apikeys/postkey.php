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

if ($s_accountrole == "admin"){
	$posted_list = array();
	$posted_list["apikey"] = $_POST["apikey"];
	$posted_list["comment"] = $_POST["comment"];
	$posted_list["type"] = $_POST["type"];
			
	if(save_key($posted_list) == true){
		echo "<font color='green'><strong>OK</font> - Apikey opgeslagen</strong>";
	} else {
		echo "<font color='red'><strong>Fout bij het opslaan</strong></font>";
	}
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function save_key($posted_list){
        global $db;
        $result = $db->AutoExecute("apikeys", $posted_list, 'INSERT');
        return $result;
}

?>
