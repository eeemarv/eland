<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if (!(isset($s_id) || $s_accountrole == "user" || $s_accountrole == "admin")){
	header("Location: ".$rootpath."login.php");
	exit;
}

show_addlink($rootpath);
show_ptitle();
show_msgdiv();

////////////////////////////////////////////////////////////////////////////

function show_addlink($rootpath){
	global $s_id;
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	echo '<li><a href="'. $rootpath . 'messages/edit.php?mode=new">Vraag/Aanbod toevoegen</a></li>';
	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";

	//echo "<div class='border_b'>| ";
	//echo "<a href='mymsg_add.php'>Mijn Vraag & Aanbod toevoegen</a> |</div>";
}

function show_ptitle(){
	echo "<h1>Mijn Vraag & Aanbod</h1>";
}

function show_msgdiv(){
	echo "<div id='msgdiv'></div>";
	$url="mymsg_render.php";
	echo "<script type='text/javascript'>showloader('msgdiv');loadurlto('$url','msgdiv')</script>";
}

include($rootpath."includes/inc_footer.php");
