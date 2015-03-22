<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!(isset($s_id) && ($s_accountrole == "admin"))){
	header("Location: ".$rootpath."login.php");
}

$id = $_GET["id"];
if(!isset($id)){
	header("Location: overview.php");
	exit;
}

$contacttype = get_contacttype($id);

if (in_array($contacttype['abbrev'], array('mail', 'tel', 'gsm', 'adr', 'web')))
{
	$alert->warning('Beschermd contact type.');
	header("Location: overview.php");
	exit;	
}

if(isset($_POST["zend"])){
	$posted_list = array();
	$posted_list["name"] = $_POST["name"];
	$posted_list["abbrev"] = $_POST["abbrev"];
	if ($_POST["protect"] == TRUE){
		$posted_list["protect"] = TRUE;
	}else{
		$posted_list["protect"] = FALSE;
	}

	echo "Posted protect value is " .$_POST["protect"];
	$posted_list["id"] = $_GET["id"];
	$error_list = validate_input($posted_list);

	if (!empty($error_list)){
		show_form($posted_list, $error_list);
	}else{
		update_contacttype($id, $posted_list);
		$alert->success('Contact type aangepast.');
		header('Location: ' . $rootpath . 'type_contact/overview.php');
		exit;
	}

	$alert->error('Fout in één of meer velden.');
}

include($rootpath."includes/inc_header.php");

echo "<h1>Contacttype aanpassen</h1>";
show_form($contacttype, $error_list);

include($rootpath."includes/inc_footer.php");

////////////////

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["name"])|| (trim($posted_list["name"] )=="")){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>Type contact</strong> in!</font>";
	}
	return $error_list;
}

function update_contacttype($id, $posted_list){
  	global $db;
	$posted_list["mdate"] = date("Y-m-d H:i:s");
	echo "Protect is " .$posted_list["protect"];
	$result = $db->AutoExecute("type_contact", $posted_list, 'UPDATE', "id=$id");

}

function show_form($contacttype, $error_list){
	echo "<div class='border_b'><p>";
	echo "<form action='edit.php?id=".$contacttype["id"]."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>Type contact </td><td>";
	echo "<input type='text' name='name' size='40' required ";
	echo "value='". htmlspecialchars($contacttype["name"],ENT_QUOTES). "'>";
	echo "</td><td>";
	if (isset($error_list["name"])){
		echo $error_list["name"];
	}
	echo "</td></tr>";

	echo "<tr><td valign='top' align='right'>Afkorting </td><td>";
	echo "<input type='text' name='abbrev' size='40' required ";
	echo "value='". htmlspecialchars($contacttype["abbrev"],ENT_QUOTES). "'>";
	echo "</td><td></td></tr>";
/*	echo "<tr><td valign='top' align='right'>Beschermd </td><td>";
	if($contacttype["protect"] == 1) {
        	echo "<input type='checkbox' name='protect' value='1' CHECKED>";
	} else {
		echo "<input type='checkbox' name='protect'>";
	} 

	echo "</td><td></td></tr>";*/

	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' value='Opslaan' name='zend'>";
	echo "</td><td>&nbsp;</td></tr></table>";
	echo "</form>";
	echo "</p></div>";
}

function get_contacttype($id){
global $db;
	$query = "SELECT * FROM type_contact WHERE id=".$id;
	$contacttype = $db->GetRow($query);
	return $contacttype;
}
