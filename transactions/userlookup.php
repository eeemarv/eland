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

if(isset($s_id)){
        if($s_accountrole == "user" || $s_accountrole == "admin"){
 		show_ptitle();
        } else {
                echo "<script type='text/javascript'>self.close();</script>";
        }

	$user = get_user($s_id);
	$balance = $user["saldo"];

	show_forms();
	show_help();
	show_closebutton();
}else{
	echo "<script type='text/javascript'>self.close();</script>";
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_closebutton(){
	echo "<table border=0 width='100%'><tr><td align='right'><form id='closeform'>";
	echo "<input type='button' id='close' value='Sluiten' onclick=\"window.opener.document.getElementById('letscode_to').value=document.getElementById('letscode').value;self.close();\">";
	echo "<form></td></tr></table>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_ptitle(){
	echo "<h1>Gebruiker opzoeken op naam</h1>";
}

function show_help(){
	echo "<p><i><small>Als er meerdere resultaten voor je opzoeking zijn wordt de eerste gebruikt, type een groter stuk van de naam als het resultaat niet correct is</small></i></p>";
}

function show_forms(){
	global $s_accountrole;
	echo "<script type='text/javascript' src='/js/userlookup.js'></script>";
	echo "<form action=\"javascript:document.getElementById('letscode').value = 'Zoeken...'; lookupuser();\" name='userform' id='userform'>";
	echo "<table cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td align='right'>";
	echo "Zoekterm";
	echo "</td><td>";
	echo "<input type='hidden' name='letsgroup' id='letsgroup' size='10'>";
	echo "<input type='text' name='name' id='name' size='30'>";
	echo "</td>";
	echo "<td align='right'>";
        echo "<input type='submit' name='zend' id='zend' value='Opzoeken'>";
	echo "</td></tr></table>";
	echo "</form>";
	echo "<script type='text/javascript'>document.getElementById('letsgroup').value = window.opener.document.getElementById('letsgroup').value;</script>";

	echo "<form action=''name='infoform' id='infoform'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
        echo "<tr><td align='right'>";
        echo "Letscode";
        echo "</td><td>";
        echo "<input type='text' name='letscode' id='letscode' size='10' readonly>";
        echo "</td></tr>";
	echo "<tr><td align='right'>";
        echo "Naam";
        echo "</td><td>";
        echo "<input type='text' name='fullname' id='fullname' size='40' readonly>";
        echo "</td></tr>";
	echo "<tr><td colspan=2>";
	echo "</td></tr></table>";
        echo "</form>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
