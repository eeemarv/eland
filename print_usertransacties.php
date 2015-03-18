<?php
ob_start();
$rootpath = "";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (isset($_GET["id"])){
		$id = $_GET["id"];
		$user = getuser($id);
		show_ptitle($id, $user);
		$transactions = get_all_transactions($id);
		show_all_transactions($transactions, $user);
		$balance = $user["saldo"];
		$currency = readconfigfromdb("currency");
		show_balance($balance,$currency);
}else{
			redirect_useroverview();
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_balance($balance,$currency){
	echo "<div class='border_b'>";
	echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr><td>&#160;</td></tr>";
	echo "<tr class='even_row'>";
	echo "<td><strong>{$currency}stand</strong></td></tr>";
	echo "<tr><td>";
	echo $balance;
	echo "</td></tr></table>";
}

function get_all_transactions($user_id){
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
	$query .= " AND (transactions.id_from = ".$user_id." OR transactions.id_to = ".$user_id.")";

	if (isset($trans_orderby)){
		$query .= " ORDER BY transactions.".$trans_orderby. " ";
	}
	else {
		$query .= " ORDER BY transactions.date DESC";
	}
	$transactions = $db->GetArray($query);
	return $transactions;
}

function show_all_transactions($transactions, $user){
	global $rootpath;

	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td nowrap valign='top'><strong>";
	echo "Datum";
	echo "</strong></td><td valign='top'><strong>Van</strong></td>";
	echo "<td><strong>Aan</strong></td><td><strong>";
	echo "Bedrag";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Dienst";
	echo "</strong></td></tr>";
	$rownumb=0;
	foreach($transactions as $key => $value){
		$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td nowrap valign='top'>";
		echo $value["datum"];
		echo "</td><td nowrap valign='top'>";
		echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
		echo "</td><td valign='top' nowrap>";
		echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
		echo "</td><td valign='top' nowrap>";
		if ($value["fromusername"] == $user["name"]){
		 	echo "-".$value["amount"];
		}else{
			echo "+".$value["amount"];
		}

		echo "</td><td valign='top'>";
		//echo "<a href='".$rootpath."transactions/view.php?id=".$value["transid"]."'>";
		echo htmlspecialchars($value["description"],ENT_QUOTES);

		echo "</td></tr>";
	}
	echo "</table></div>";
}

function show_ptitle($id, $user){
	echo "<h1>Lets Transactielijst ".$user["name"];
	echo  " ";
	echo date("d-m-Y");
	echo " </h1>";
}

function getuser($id){
	return readuser($id);
}

function redirect_useroverview($rootpath){
	header("Location: ".$rootpath."users/overview.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}
