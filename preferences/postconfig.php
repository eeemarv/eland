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
		if(save_setting($posted_list, $mysetting) == true){
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

function save_setting($posted_list,$setting){
        global $db;
	$query = "UPDATE config SET \"default\"='0', value='"; 
	$query .= $posted_list["value"];
	$query .= "' WHERE setting='";
	$query .= $setting;
	$query .= "'";
        $result = $db->Execute($query);
        if($result == true){
				loadredisfromdb();
                setstatus('Instelling gewijzigd',0);
        } else {
                setstatus('Instelling niet gewijzigd',0);
        }
        return $result;
}

?>
