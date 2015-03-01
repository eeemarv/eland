<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_mailfunctions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

$id = $_POST["id"];

if($s_accountrole == "admin"){
	if(update_newsitem($id) == TRUE){
		echo "Nieuwsbericht goedgekeurd";
	} else {
		echo "<strong>Goedkeuren nieuwsbericht mislukt</strong>";
	}
} else {
	echo "<strong>Onvoldoende rechten</strong>";
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function update_newsitem($id){
	global $db;
	$posted_list["approved"] = 1;
	$result = $db->AutoExecute("news", $posted_list, 'UPDATE', "id=$id");
	return $result;
}

?>
