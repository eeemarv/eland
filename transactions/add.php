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
 		show_ptitle(readconfigfromdb("currency"));
        } else {
                echo "<script type='text/javascript'>self.close();</script>";
        }

	$user = get_user($s_id);
	$balance = $user["saldo"];

	$list_users = get_users($s_id);
	show_balance($balance, $user);
	show_form($list_users, $user, $balance,$s_letscode);
	show_serveroutputdiv();
	show_buttons();
}else{
	echo "<script type='text/javascript'>self.close();</script>";
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_balance($balance, $user){
	$currency = readconfigfromdb("currency");
	$minlimit = $user["minlimit"];

	echo "<div id='baldiv'>";
	echo "<p><strong>Huidige {$currency}stand: ".$balance."</strong> || ";
	echo "<strong>Limiet minstand: ".$minlimit."</strong></p>";
	echo "</div>";
}

function show_buttons(){
	echo "<table border=0 width='100%'><tr><td align='left'>";
	$myurl="userlookup.php";
	echo "<form id='lookupform'><input type='button' id='lookup' value='LETSCode opzoeken' onclick=\"javascript:newwindow=window.open('$myurl','Lookup','width=600,height=500,scrollbars=yes,toolbar=no,location=no,menubar=no');\"></form>";

	//echo "<script type='text/javascript'>newwindow.document.getElementById('letsgroup').value='document.getElementById('letsgroup').value;</script>";
	//echo "<script type='text/javascript'>tmp.write('Testing');</script>";
	echo "</td><td align='right'>";
	echo "<form id='closeform'><input type='button' id='close' value='Sluiten' onclick='self.close()'></form>";
	echo "</td></tr></table>";
}

function show_notify(){
	echo "<p><small><i>LETS Groep moet je enkel wijzigen voor Interlets transacties met andere eLAS installaties,<br>de standaard selectie is je eigen groep (of groepen op dezelfde installatie). </i></small></p>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_ptitle($currency){
	echo "<h1>{$currency} uitschrijven</h1>";
}

function redirect_overview(){
	header("Location: mytrans_overview.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_form($list_users, $user, $balance, $letscode){
	global $s_accountrole;
	global $s_letscode;
	$date = date("Y-m-d");

	echo "<script type='text/javascript' src='/js/posttransaction.js'></script>";
	echo "<script type='text/javascript' src='/js/userinfo.js'></script>";
	$currency = readconfigfromdb("currency");
	echo "<div id='transformdiv'>";
	echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('transform'));\" name='transform' id='transform'>";
	echo "<input name='balance' id='balance' type='hidden' value='".$balance."' >";
	echo "<input name='minlimit' type='hidden' id='minlimit' value='".$user["minlimit"]."' >";
	echo "<table cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td align='right'>";
	echo "Van";
	echo "</td><td>";
	//echo "<input name='letscode_from' id='letscode_from' ";
	echo "<select name='letscode_from' id='letscode_from' accesskey='2'\n";
	if($s_accountrole != "admin") {
                echo " DISABLED";
	}
	echo " onchange=\"javascript:document.getElementById('baldiv').innerHTML = ''\">";
	foreach ($list_users as $value){
		echo "<option value='".$value["letscode"]."' >";
		echo htmlspecialchars($value["fullname"],ENT_QUOTES) ." (" .$value["letscode"] .")";
		echo "</option>\n";
	}
	echo "</select>\n";

	echo "</td><td width='150'><div id='fromoutputdiv'></div>";
	echo "</td></tr>";

	echo "<tr><td valign='top' align='right'>Datum</td><td>";
        echo "<input type='text' name='date' id='date' size='18' value='" .$date ."'";
	if($s_accountrole != "admin") {
                echo " DISABLED";
        }
        echo ">";
        echo "</td><td>";
        echo "</td></tr><tr><td></td><td>";
        echo "</td></tr>";

	echo "<tr><td align='right'>";
        echo "Aan LETS groep";
        echo "</td><td>";
        echo "<select name='letgroup' id='letsgroup' onchange=\"document.getElementById('letscode_to').value='';\">\n";
	$letsgroups = get_letsgroups();
	foreach($letsgroups as $key => $value){
		$id = $value["id"];
		$name = $value["groupname"];
		echo "<option value='$id'>$name</option>";
	}
	echo "</select>";
	echo "</td><td>";
	echo "</td></tr><tr><td></td><td>";
	echo "<tr><td align='right'>";
	echo "Aan LETSCode";
	echo "</td><td>";
	echo "<input type='text' name='letscode_to' id='letscode_to' size='10' onchange=\"javascript:showsmallloader('tooutputdiv');loaduser('letscode_to','tooutputdiv')\">";
	echo "</td><td><div id='tooutputdiv'></div>";
	echo "</td></tr><tr><td></td><td>";
	echo "</td></tr>";

	echo "<tr><td valign='top' align='right'>Aantal {$currency}</td><td>";
	echo "<input type='text' id='amount' name='amount' size='10' ";
	echo ">";
	echo "</td><td>";
	echo "</td></tr>";
	echo "<tr><td></td><td>";
	echo "</td></tr>";

	echo "<tr><td valign='top' align='right'>Dienst</td><td>";
	echo "<input type='text' name='description' id='description' size='40' MAXLENGTH='60' ";
	echo ">";
	echo "</td><td>";
	echo "</td></tr><tr><td></td><td>";
	echo "</td></tr>";
	echo "<tr><td colspan='3' align='right'>";
	echo "<input type='submit' name='zend' id='zend' value='Overschrijven'>";
	echo "</td></tr></table>";
	echo "</form>";
	echo "<script type='text/javascript'>loaduser('letscode_from','fromoutputdiv')</script>";
	echo "</div>";

	echo "<script type='text/javascript'>document.getElementById('letscode_from').value = '$s_letscode';</script>";
}

function show_user($user){
	echo $user["name"]." ".$user["letscode"];
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
