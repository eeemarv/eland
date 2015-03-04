<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

$trans_orderby = $_GET["trans_orderby"];

if(isset($s_id) && ($s_accountrole == "admin")){

	show_ptitle();
	$transactions = get_all_transactions($trans_orderby);
	show_all_transactions($transactions);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_all_transactions($transactions){

	echo '"Datum","Van","Aan","Bedrag","Dienst"';
	echo "\r\n";
	foreach($transactions as $key => $value){
		echo '"';
		echo $value["datum"];
		echo '","';
		echo $value["fromletscode"]. " (" .trim($value["fromusername"]).")";
		echo '","';
		echo $value["toletscode"]. " (" .trim($value["tousername"]).")";
		echo '","';
		echo $value["amount"];
		echo '","';
		echo $value["description"];
		echo '"';
		echo "\r\n";
	}
}

function get_all_transactions($trans_orderby){
	global $db;
	$query = "SELECT *, ";
	$query .= " transactions.id AS transid, ";
	$query .= " fromusers.id AS userid, ";
	$query .= " fromusers.name AS fromusername, tousers.name AS tousername, ";
	$query .= " fromusers.letscode AS fromletscode, tousers.letscode AS toletscode, ";
	$query .= " transactions.date AS datum ";
	$query .= " FROM transactions, users  AS fromusers, users AS tousers";
	$query .= " WHERE transactions.id_to = tousers.id";
	$query .= " AND transactions.id_from = fromusers.id";
	if (isset($trans_orderby)){
		$query .= " ORDER BY transactions.".$trans_orderby. " ";
	}
	else {
		$query .= " ORDER BY transactions.date DESC";
	}
	$transactions = $db->GetArray($query);
	return $transactions;
}

function show_ptitle(){
#	echo "<h1>Transacties ";
#	echo date("d-m-Y");
#	echo " </h1>";
	header("Content-disposition: attachment; filename=marva-transactions".date("Y-m-d").".csv");
	header("Content-Type: application/force-download");
	header("Content-Transfer-Encoding: binary");
	header("Pragma: no-cache");
	header("Expires: 0");
}

?>
