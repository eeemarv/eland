<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

$groupid = $_GET["letsgroup"];

include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");

if(!isset($s_id)){
	echo "<script type=\"text/javascript\">self.close();</script>";
}

$letsgroup = get_letsgroup($groupid);
	if($s_accountrole == "user" || $s_accountrole == "admin"){
	show_ptitle($letsgroup["groupname"]);
	if($letsgroup["apimethod"] == 'elassoap'){
		show_outputdiv($groupid);
	} else {
		echo "<b>Deze groep draait geen eLAS, kan geen connectie maken</b>";
	}
	} else {
			echo "<script type=\"text/javascript\">self.close();</script>";
	}

show_closebutton();


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_closebutton(){
	echo "<table border=0 width='100%'><tr><td align='right'><form id='closeform'>";
	echo "<input type='button' id='close' value='Sluiten' onclick='self.close()'>";
	echo "<form></td></tr></table>";
}

function show_outputdiv($groupid){
	echo "<script type='text/javascript' src='/js/redirect.js'></script>";
	$url = "/interlets/doredirect.php?letsgroup=" .$groupid;
	echo "<div id='output'>Connectie met eLAS wordt gemaakt... <img src='/gfx/ajax-loader.gif' ALT='loading'>";
	echo "<script type='text/javascript'>doredirect('$url');</script>";

        //echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
        //echo "<script type=\"text/javascript\">loadurl('doredirect.php?letsgroup=" .$groupid ."')</script>";
        echo "</div>";
}

function show_ptitle($groupname){
	echo "<h1>Interlets login naar $groupname</h1>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
