<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

include("inc_balance.php");

$user_date = $_GET["date"];
$user_prefix = $_GET["prefix"];

show_ptitle($user_date);
$users = get_users($user_prefix);
show_csv_user_balance($users,$user_date);

///////////////////

function show_ptitle($user_date)
{
        header("Content-disposition: attachment; filename=marva-balances-".$user_date .".csv");
        header("Content-Type: application/force-download");
        header("Content-Transfer-Encoding: binary");
        header("Pragma: no-cache");
        header("Expires: 0");
}

function show_csv_user_balance($users,$user_date)
{
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
