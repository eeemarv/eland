<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_passwords.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");

if (isset($s_id)){
	$errorlist = array();
	show_ptitle();
	if(isset($_POST["zend"])){
		
		$posted_list = array();
		$posted_list["pw1"] = $_POST["pw1"];
		$posted_list["pw2"] = $_POST["pw2"];
		$errorlist = validate_input($posted_list,$configuration);
			
		if (!empty($errorlist)){
			show_pwform($errorlist, $s_id);
		}else{
			update_pw($s_id, $posted_list);
		}
	}else{
		show_pwform($errorlist, $s_id);
		show_serveroutputdiv();
		show_closebutton();
	}
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_closebutton(){
        echo "<table border=0 width='100%'><tr><td align='right'><form id='closeform'>";
        echo "<input type='button' id='close' value='Sluiten' onclick='self.close()'>";
        echo "<form></td></tr></table>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_pwform($errorlist, $id){
	$randpw = generatePassword(9);
	echo "<div class='border_b'>";
	echo "<script type='text/javascript' src='/js/postpassword.js'></script>";
	echo "<form id='pwform' action=\"javascript:showloader('serveroutput'); get(document.getElementById('pwform'));\">";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>Paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='text' id='pw1' name='pw1' size='30' value='$randpw' >";
	echo "</td>";
	echo "</tr>";
	echo "<tr><td valign='top' align='right'>Herhaal paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='test' id='pw2' name='pw2' size='30' value='$randpw' >";
	echo "</td>";
	echo "</tr>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' id='zend' value='Passwoord wijzigen' name='zend'>";
	echo "</td><td>&nbsp;</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";

}

function show_ptitle(){
	echo "<h1>Paswoord veranderen</h1>";
}
function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}
include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
