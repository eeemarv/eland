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
	
include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");

if($s_accountrole == "admin"){
	show_ptitle();
	showrefresh();
	show_outputdiv();
} else {
	echo "<script type=\"text/javascript\">self.close();</script>";
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Interletstransacties in verwerking</h1>";
}

function showrefresh(){
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        echo "<li><a href='#' onclick=window.location.reload()>Herladen</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}


function show_outputdiv(){
	echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
	echo "<script type=\"text/javascript\">loadurl('renderqueue.php');</script>";
	echo "</div>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
