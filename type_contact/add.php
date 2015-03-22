<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!(isset($s_id) && ($s_accountrole == "admin"))){
	header("Location: ".$rootpath."login.php");
	exit;
}

if (isset($_POST["zend"])){
	$posted_list = array();
	$posted_list["name"] = $_POST["name"];
	$posted_list["abbrev"] = $_POST["abbrev"];
	$error_list = validate_input($posted_list);

	if (empty($error_list)){
		$db->AutoExecute("type_contact", $posted_list, 'INSERT');
		header("Location: overview.php");
		$alert->success('Contact type toegevoegd.');
		redirect_overview();
		exit;
	}

	$alert->error('Corrigeer één of meerdere velden.');
}

include($rootpath."includes/inc_header.php");

echo "<h1>Contacttype toevoegen</h1>";

echo "<div class='border_b'><p>";
echo "<form method='POST' action='add.php'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td valign='top' align='right'>Contacttype </td><td>";
echo "<input type='text' name='name' size='30' required ";
if (isset($posted_list["name"])){
	echo  "value ='".$posted_list["name"]."'";
}
echo "></td><td>";
if(isset($error_list["name"])){
	echo $error_list["name"];
}
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Afkorting</td>";
echo "<td>";
echo "<input type='text' name='abbrev' size='30' required ";
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

include($rootpath."includes/inc_footer.php");

/////////////

function validate_input($posted_list){
	global $db;
	
	$error_list = array();
	if (!isset($posted_list["name"])|| (trim($posted_list["name"])=="")){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>contacttype</strong> in!</font>";
	}

	$types_asc = $db->GetAssoc('SELECT abbrev, name FROM type_contact');

	if (isset($types_asc[$posted_list["abbrev"]])){
		$error_list["abbrev"]="<font color='#F56DB5'>bestaat reeds!</font>";
	}	
	return $error_list;
}
