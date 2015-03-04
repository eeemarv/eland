<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

#include($rootpath."includes/inc_header.php");
#include($rootpath."includes/inc_nav.php");

include("inc_balance.php");

$user_date = $_GET["date"];
$user_prefix = $_GET["prefix"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle($user_date);
        $users = get_users($user_prefix);

	show_user_balance($users,$user_date);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle($user_date){
	echo "<h1>Stand rekeningen op " .$user_date ."</h1>";
}

#include($rootpath."includes/inc_sidebar.php");
#include($rootpath."includes/inc_footer.php");
?>
