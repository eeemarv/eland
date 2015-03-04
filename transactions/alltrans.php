<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

$trans_orderby = $_GET["trans_orderby"];
$asc = $_GET["asc"];

if($s_accountrole == "user" || $s_accountrole == "admin" || $s_accountrole == "interlets"){
	show_ptitle();
	show_outputdiv($trans_orderby, $asc);
} else {
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

function show_outputdiv($trans_orderby, $asc){
	$loadurl = 'rendertransactions.php?trans_orderby='.$trans_orderby.'&asc='.$asc;
	echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
	echo "<script type=\"text/javascript\">loadurl('$loadurl');</script>";
	echo "</div>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
