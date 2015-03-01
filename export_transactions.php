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

if (isset($_GET["userid"])) {
    $userid=$_GET["userid"];
}

$trans_orderby = $_GET["trans_orderby"];

if(isset($s_id) && ($s_accountrole == "admin")){

	if (isset($userid)){
	    show_ptitle_with_id($userid);
	    $transactions = get_all_transactions_by_userid($userid,$trans_orderby);
	} else {
	    show_ptitle();
	    $transactions = get_all_transactions($trans_orderby);
	}
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
	
	echo '"id","Creatiedatum","Transactiedatum","Van","Aan","Bedrag","Dienst","Ingebracht door"';
	echo "\r\n";
foreach($transactions as $key => $value){
		echo '"';
		echo $value["id"];
		echo '","';
		echo $value["creadate"];
		echo '","';
		echo $value["transdate"];
		echo '","';
		echo $value["fromletscode"]. " (" .trim($value["fromusername"]).")";
		echo '","';
		echo $value["toletscode"]. " (" .trim($value["tousername"]).")";
		echo '","';
		echo $value["amount"];
		echo '","';
		echo $value["description"];
echo '","';
		echo $value["crealetscode"];
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
	$query .= " fromusers.letscode AS fromletscode, cusers.letscode AS crealetscode, tousers.letscode AS toletscode, ";
	$query .= " transactions.date AS transdate, ";
	$query .= " transactions.cdate AS creadate ";
	$query .= " FROM transactions, users  AS fromusers, users AS tousers, users AS cusers ";
	$query .= " WHERE transactions.id_to = tousers.id";
	$query .= " AND transactions.id_from = fromusers.id";
$query .= " AND transactions.creator = cusers.id";
	if (isset($trans_orderby)){
		$query .= " ORDER BY transactions.".$trans_orderby. " ";
	}
	else {
		$query .= " ORDER BY transactions.date DESC";
	}    
	$transactions = $db->GetArray($query);
	return $transactions;
}

function get_all_transactions_by_userid($userid,$trans_orderby){
	global $db;
	$query = "SELECT *, ";
	$query .= " transactions.id AS transid, ";
	$query .= " fromusers.id AS userid, ";
	$query .= " fromusers.name AS fromusername, tousers.name AS tousername, ";
	$query .= " fromusers.letscode AS fromletscode, cusers.letscode AS crealetscode, tousers.letscode AS toletscode, ";
	$query .= " transactions.date AS transdate, ";
	$query .= " transactions.cdate AS creadate ";
	$query .= " FROM transactions, users  AS fromusers, users AS tousers, users AS cusers ";
	$query .= " WHERE ( transactions.id_to = tousers.id";
	$query .= " AND transactions.id_from = fromusers.id";
	$query .= " AND transactions.creator = cusers.id)";
	$query .= " AND ( transactions.id_to = $userid OR transactions.id_from = $userid)";
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
	header("Content-disposition: attachment; filename=elas-transactions".date("Y-m-d").".csv");
	header("Content-Type: application/force-download");
	header("Content-Transfer-Encoding: binary");
	header("Pragma: no-cache");
	header("Expires: 0");
}

function show_ptitle_with_id($userid){
#	echo "<h1>Transacties ";
#	echo date("d-m-Y");
#	echo " </h1>";
	header("Content-disposition: attachment; filename=elas-transactions-$userid-".date("Y-m-d").".csv");
	header("Content-Type: application/force-download");
	header("Content-Transfer-Encoding: binary");
	header("Pragma: no-cache");
	header("Expires: 0");
}




?>

