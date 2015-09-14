<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");

include("inc_transperuser.php");

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];
$user_prefix = $_GET["prefix"];

header("Content-disposition: attachment; filename=marva-transactions-".date("Y-m-d").".csv");
header("Content-Type: application/force-download");
header("Content-Transfer-Encoding: binary");
header("Pragma: no-cache");
header("Expires: 0");

$transactions = get_all_transactions($user_userid,$user_datefrom,$user_dateto,$user_prefix);

echo '"Datum","Van","Aan","Bedrag","Dienst"';
echo "\r\n";
foreach($transactions as $key => $value){
echo "\"";
		echo $value["datum"];
echo "\",";
echo "\"";
		echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
echo "\",";
echo "\"";
		echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
echo "\",";
echo "\"";
		echo $value["amount"];
echo "\",";
echo "\"";
		echo htmlspecialchars($value["description"],ENT_QUOTES);
echo "\"";

		echo "\r\n";
}

