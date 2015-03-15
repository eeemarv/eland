<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (isset($s_id)){
	if(add_contact($_POST) == true){
		echo "OK - Contact toegevoegd";
	} else {
		echo "Fout, contact niet opgeslagen";
	}
}

//////////////////

function validate_input($posted_list){
  	global $db;
	$error_list = array();
	if (empty($posted_list["value"]) || (trim($posted_list["value"]) == "")){
		$error_list["value"] = "<font color='#F56DB5'>Vul <strong>waarde</strong> in!</font>";
	}

	$query =" SELECT * FROM type_contact ";
	$query .=" WHERE  id = '".$posted_list["id_type_contact"]."' ";
	$rs = $db->Execute($query);
    	$number = $rs->recordcount();
	if( $number == 0 ){
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}
	return $error_list;
}

function add_contact($posted_list){
	global $db;
	$result = $db->AutoExecute("contact", $posted_list, 'INSERT');
	return $result;
}
