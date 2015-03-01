<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_apikeys.php"); 
require_once($rootpath."includes/inc_mailfunctions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");
$my_setting = $_GET["setting"];

if(isset($s_id)){
        if(!$s_accountrole == "admin"){
                redirect_login($rootpath);
        }
	show_ptitle();
	show_form();
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

function show_ptitle(){
	echo "<h1>Apikey toevoegen</h1>";
}

function redirect_overview(){
	header("Location: mytrans_overview.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}
	
function show_form(){
	global $s_accountrole;
	$mykey = generate_apikey();
	echo "<script type='text/javascript' src='/js/postapikey.js'></script>";
	echo "<div id='apikeydiv' class='border_b'>";
	echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('apikeyform'));\" name='apikeyform' id='apikeyform'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td align='right'>";
	echo "Apikey";
	echo "</td><td>";
	echo "<input type='text' name='apikey' id='apikey' value='";
	echo $mykey;
	echo "' READONLY>";
	echo "</td></tr>";
	
	echo "<tr><td align='right'>Type</td><td>";
	echo "<select name='keytype' id='keytype'>";
	echo "<option value='interlets' >Interlets</option>";
	echo "</select>";
	echo "</td></tr>";
	
	echo "<tr><td align='right'>Comment</td><td>";
	echo "<input type='text' name='comment' id='comment' size='50'>";
	echo "</td></tr>";
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
