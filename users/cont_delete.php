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

if(isset($s_id) && ($s_accountrole == "admin")){
	$cid = $_GET["cid"];
	$uid = $_GET["uid"];
	if(isset($cid)){
		delete_contact($cid);
		redirect_view($uid);
	}else{
		redirect_overview();
	}
}else{
	redirect_login($rootpath);
}
////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function delete_contact($cid){
	global $db;
	$query = "DELETE FROM contact WHERE id =".$cid ;
	$result = $db->Execute($query);
}

function redirect_view($uid){
	header("Location: view.php?id=$uid");
}

function redirect_overview(){
	header("Location: overview.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

