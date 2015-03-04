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

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if (isset($s_id)){
	if(isset($_GET["id"])){
		$id = $_GET["id"];
		delete_oid($id);
		redirect_mydetails_view();
	}else{
		redirect_mydetails_view();
	}

}else{
	redirect_login($rootpath);
}
////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function delete_oid($id){
	global $db;
	global $s_id;
	// only allow removal of one's own details
	$query = "DELETE FROM openid WHERE id =".$id ." AND user_id = " .$s_id;
	$result = $db->Execute($query);
	if($result == TRUE) {
		setstatus("OpenID $id verwijderd", 0);
	} else {
		setstatus("OpenID $id NIET verwijderd", 1);
	}
}

function redirect_mydetails_view(){
	header("Location:  mydetails.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
