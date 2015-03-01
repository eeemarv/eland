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

function show_addlink($rootpath){
	echo "<div class='border_b'>| <a href='add.php' accesskey='N'>Transactie toevoegen</a> | ";
	echo "<a href='bijdrage_add.php' >Maandelijkse bijdrage</a> | ";
	echo "<a href='".$rootpath."export_transactions.php'>Export</a> | </div>";
}

function show_ptitle(){
	echo "<h1>Overzicht transacties</h1>";
}

function show_all_transactions($transactions){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td nowrap valign='top'><strong>";
	echo "<a href='overview.php?trans_orderby=cdate'>Creatieatum</a>";
	echo "</strong></td>";
echo "<td valign='top'><strong><a href='overview.php?trans_orderby=date'>Transactiedatum</a></strong></td>";
	echo "<td valign='top'><strong>Van</strong></td>";
	echo "<td><strong>Aan</strong></td><td><strong>";
	echo "<a href='overview.php?trans_orderby=amount'>Bedrag</a>";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "<a href='overview.php?trans_orderby=description'>Dienst</a>";
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
		echo $value["cdatum"];
		echo "</td>";
	echo "<td nowrap valign='top'>";
		echo $value["datum"];
		echo "</td>";
		echo "<td nowrap valign='top'>";
		echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
		echo "</td><td valign='top' nowrap>";
		echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
		echo "</td><td valign='top' nowrap>";
		echo $value["amount"];
		echo "</td><td valign='top'>";
		echo "<a href='view.php?id=".$value["transid"]."'>";
		echo htmlspecialchars($value["description"],ENT_QUOTES);
		echo "</a> ";
		echo "</td></tr>";
	}
	echo "</table></div>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
