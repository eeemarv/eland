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

echo "<h1>Stand rekeningen op " .$user_date ."</h1>";
$users = get_users($user_prefix);
show_user_balance($users,$user_date);
