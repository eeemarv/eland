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

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	if (isset($_POST["zend"])){
		$posted_list = array();
		$posted_list["name"] = $_POST["name"];
		$posted_list["abbrev"] = $_POST["abbrev"];
		$error_list = validate_input($posted_list);

		if (!empty($error_list)){
			show_form($error_list, $posted_list);
		}else{
			insert_contacttype($posted_list);
			redirect_overview();
		}
	}else{
		show_form($error_list, $posted_list);
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Contacttype toevoegen</h1>";
}

function redirect_overview(){
	header("Location: overview.php");
}

function insert_contacttype($posted_list){
	global $db;
    $db->AutoExecute("type_contact", $posted_list, 'INSERT');
}

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["name"])|| (trim($posted_list["name"])=="")){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>contacttype</strong> in!</font>";
	}
	return $error_list;
}

function show_form($error_list, $posted_list){
	echo "<div class='border_b'><p>";
	echo "<form method='POST' action='add.php'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>Contacttype </td><td>";
	echo "<input type='text' name='name' size='30' ";
	if (isset($posted_list["name"])){
		echo  "value ='".$posted_list["name"]."'>";
	}
	echo "</td><td>";
	if(isset($error_list["name"])){
		echo $error_list["name"];
	}
	echo "</td></tr>";

	echo "<tr><td valign='top' align='right'>Afkorting</td>";
	echo "<td>";
	echo "<input type='text' name='abbrev' size='30' ";
	if (isset($posted_list["abbrev"])){
		echo  "value ='".$posted_list["abbrev"]."'>";
	}
	echo "</td><td>";
	echo "</td></tr>";

	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' name='zend' value='Toevoegen'>";
	echo "</td><td>&nbsp;</td></tr></table>";
	echo "</form>";
	echo "</p></div>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
