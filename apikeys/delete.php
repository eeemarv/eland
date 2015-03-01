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

if(isset($s_id)){
	show_ptitle();
	$id = $_GET["id"];
	if(isset($id)){
		if(isset($_POST["zend"])){
			delete_key($id);
			echo "<script type=\"text/javascript\">self.close(); window.opener.location.reload()</script>";
		}else{
			show_form($id);
		}
	}else{ 
		redirect_view();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Apikey verwijderen</h1>";
}

function delete_key($id){
	global $db;
	$query = "DELETE FROM apikeys WHERE id=" .$id;
	$db->Execute($query);
}

function show_form($id){
	echo "<form action='delete.php?id=".$id ."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";
	echo "<tr>\n";
	echo "<td>Apikey verwijderen? <input type='submit' value='Verwijderen' name='zend'></td>\n";
	echo "</tr>\n\n</table>";
	echo "</form>";
}

function redirect_view(){
	header("Location: mydetails_view.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>


