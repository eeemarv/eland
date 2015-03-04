<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_transactions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id)){
	if (isset($_GET["id"])){
		$id = $_GET["id"];
		$transaction = get_transaction($id);
		show_ptitle();
		show_transaction($transaction);
	}else{
		redirect_overview();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Transactie</h1>";
}

function show_transaction($transaction){
	$currency = readconfigfromdb("currency");
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr>";
	echo "<td width='150'>Datum</td>";
	echo "<td>".$transaction["datum"] ."</td>";
	echo "</tr><tr>";
	echo "<td width='150'>Creatiedatum</td>";
        echo "<td>".$transaction["cdatum"] ."</td>";
	echo "</tr><tr>";
        echo "<td width='150'>TransactieID</td>";
        echo "<td>".$transaction["transid"] ."</td>";
        echo "</tr><tr>";
	echo "<td width='150'>Account Van</td>";
        echo "<td>". $transaction["fromusername"]. " (" .trim($transaction["fromletscode"]).")</td>";
	echo "</tr><tr>";
	echo "<td width='150'>Van</td>";
        echo "<td>". $transaction["real_from"] ."</td>";
        echo "</tr><tr>";
	echo "<td width='150'>Account Aan</td>";
	echo "<td>". $transaction["tousername"]. " (" .trim($transaction["toletscode"]).")</td>";
	echo "</tr><tr>";
	echo "<td width='150'>Aan</td>";
        echo "<td>". $transaction["real_to"] ."</td>";
        echo "</tr><tr>";

	echo "<td width='150'>Waarde</td>";
	echo "<td>". $transaction["amount"] ." $currency</td>";
	echo "</tr><tr>";
        echo "<td width='150'>Omschrijving</td>";
        echo "<td>". $transaction["description"]."</td>";

	echo "</tr>";
	echo "</table></div>";

	if($s_accountrole == "admin"){
		echo "<div class='border_b'>";
		echo "|<a href='delete.php?id=".$transaction["transid"]."'> Verwijderen</a> |";
		echo "</div>";
	}
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
