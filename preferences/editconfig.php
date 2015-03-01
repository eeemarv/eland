<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_transactions.php");
require_once($rootpath."includes/inc_userinfo.php"); 
require_once($rootpath."includes/inc_mailfunctions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_smallheader.php");
#include($rootpath."includes/inc_nav.php");
include($rootpath."includes/inc_content.php");
$my_setting = $_GET["setting"];

if(isset($s_id)){
        if(!$s_accountrole == "admin"){
                redirect_login($rootpath);
        }
	show_ptitle($my_setting);
	show_form($my_setting);
	show_serveroutputdiv();
	show_closebutton();
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_closebutton(){
	echo "<table border=0 width='100%'><tr><td align='right'><form id='closeform'>";
	echo "<input type='button' id='close' value='Sluiten' onclick='window.opener.location.reload(true);
;self.close()'>";
	echo "<form></td></tr></table>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_ptitle($setting){
	echo "<h1>Instelling $setting aanpassen</h1>";
}

function redirect_overview(){
	header("Location: mytrans_overview.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}
	
function show_form($setting){
	global $s_accountrole;
	$mysetting = get_setting($setting);
	echo "<script type='text/javascript' src='/js/postsetting.js'></script>";
	echo "<div id='settingformdiv' class='border_b'>";
	echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('settingform'));\" name='settingform' id='settingform'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td align='right'>";
	echo "Waarde";
	echo "</td><td>";
	echo "<input type='text' name='mysetting' id='mysetting' value='";
	echo $setting;
	echo "' READONLY>";
	echo "<input type='text' name='myvalue' id='myvalue' size='40' ";
	echo "value='";
	echo $mysetting["value"];
	echo "'>";
	echo "</td></tr>";
	echo "<tr><td align='right'>";
        echo "Omschrijving";
        echo "</td><td><i>";
	echo $mysetting["description"];
	echo "</i></td></tr>";
	echo "<tr><td align='right'>";
        echo "Commentaar";
        echo "</td><td><i>";
        echo $mysetting["comment"];
        echo "</i></td></tr>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' name='zend' id='zend' value='opslaan'>";
	echo "</td></tr></table>";
	echo "</form>";
	echo "</div>";
}

function get_setting($key){
        global $db;
        $query = "SELECT * FROM config ";
        $query .= "WHERE setting = '" .$key ."'";
        $setting = $db->GetRow($query);
        return $setting;
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
