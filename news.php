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

include($rootpath."includes/inc_header.php");
echo "<script type='text/javascript' src='$rootpath/js/moonews.js'></script>";

if(isset($s_id)){
	show_ptitle();
	if($s_accountrole == "user" || $s_accountrole == "admin"){
		show_addlink();
	}
	show_outputdiv();
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_addlink(){
	global $rootpath;
	echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
	$myurl= $rootpath ."news/edit.php?mode=new";
	echo "<li><a href='#' onclick=window.open('$myurl','news_add','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Toevoegen</a></li>";
	echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function show_ptitle(){
	echo "<h1>Nieuws</h1>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_outputdiv(){
        echo "<div id='output'>";
        //echo "<script type=\"text/javascript\">loadurl('rendernews.php')</script>";
        echo "</div>";
}

include($rootpath."includes/inc_footer.php");
?>
