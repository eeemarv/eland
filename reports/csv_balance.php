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
        show_csv_user_balance($users,$user_date);
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
        header("Content-disposition: attachment; filename=marva-balances-".$user_date .".csv");
        header("Content-Type: application/force-download");
        header("Content-Transfer-Encoding: binary");
        header("Pragma: no-cache");
        header("Expires: 0");
}

function show_csv_user_balance($users,$user_date){

        //echo '"id","Creatiedatum","Transactiedatum","Van","Aan","Bedrag","Dienst","Ingebracht door"';
	echo '"Letscode","Naam","Saldo"';
        echo "\r\n";
	foreach($users as $key => $value){
		$value["balance"] = $value["saldo"];
		echo "\"";
                echo $value["letscode"];
		echo "\",";
		echo "\"";
                echo $value["fullname"];
		echo "\",";
		echo "\"";
                echo $value["balance"];
		echo "\"";
		
                echo "\r\n";
        }
}


#include($rootpath."includes/inc_sidebar.php");
#include($rootpath."includes/inc_footer.php");
?>
