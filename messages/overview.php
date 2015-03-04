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

if(isset($_GET["msg_orderby"])){
	$msg_orderby = $_GET["msg_orderby"];
} else {
	$msg_orderby = "messages.id";
}
$user_filterby = $_GET["user_filterby"];

if(isset($s_id)){
	if($s_accountrole == "admin"){
		showlinks($rootpath);
	}
	show_ptitle();
	show_outputdiv($user_filterby, $msg_orderby);
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
	echo "<div class='border_b'>| <a href='edit.php?mode=new'>Vraag & Aanbod toevoegen</a> | ";
	echo "<a href='".$rootpath."export_messages.php'>Export</a> | ";
	echo "</div>";
}

function showlinks($rootpath){
	global $s_id;
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        $myurl=$rootpath ."messages/edit.php?mode=new";
        echo "<li><a href='#' onclick=window.open('$myurl','addmessage','width=640,height=800,scrollbars=yes,toolbar=no,location=no,menubar=no')>Vraag & Aanbod toevoegen</a></li>";
	$myurl="$rootpath/export_messages.php";
	echo "<li><a href='#' onclick=window.open('$myurl','msgexport','width=1200,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Export</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function show_ptitle(){
	echo "<h1>Overzicht Vraag & Aanbod</h1>";
}

function show_outputdiv($user_filterby, $msg_orderby){
        echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
        echo "<script type=\"text/javascript\">loadurl('renderoverview.php?user_filterby=";
        echo $user_filterby;
	echo "&msg_orderby=";
	echo $msg_orderby;
        echo "')</script>";
        echo "</div>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
