<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_form.php");

if(isset($_POST["zend"]))
{
	$posted_list = array();
	$posted_list["id_type_contact"] = $_POST["id_type_contact"];
	$posted_list["value"] = $_POST["value"];
	$posted_list["comments"] = $_POST["comments"];

	$posted_list["flag_public"] = ($_POST["flag_public"]) ? 1 : 0;

	$error_list = validate_input($posted_list);

	if(empty($error_list))
	{
		$posted_list["id_user"] = $s_id;
		$result = $db->AutoExecute('contact', $posted_list, 'INSERT');
		$alert->success('Contact toegevoegd.');
		header('Location: mydetails.php');
		exit;
	}

	$alert->error('Contact niet toegevoegd.');
}

include $rootpath . 'includes/inc_header.php';
echo "<h1>Contact toevoegen</h1>";

$typecontacts = $db->GetAssoc('SELECT id, name FROM type_contact');

echo "<div class='border_b'>";
echo "<form method='POST' action='mydetails_cont_add.php'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr>";
echo "<td valign='top' align='right'>Type</td>";
echo "<td>";
echo '<select name="id_type_contact">';
render_select_options($typecontacts, $posted_list['id_type_contact']);
echo "</select></td>";

echo "</tr><tr><td></td><td>";
if(isset($error_list["id_type_contact"]))
{
	echo $error_list["id_type_contact"];
}
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td valign='top' align='right'>Waarde</td>";
echo "<td>";
echo "<input type='text' name='value' size='20' required ";
if (isset($posted_list["value"]))
{
	echo " value='".$posted_list["value"]."' ";
}
echo ">";
echo "</td>";
echo "</tr><tr><td></td><td>";
if(isset($error_list["value"]))
{
	echo $error_list["value"];
}
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td valign='top' align='right'>Commentaar</td>";
echo "<td>";
echo "<input type='text' name='comments' size='30' ";
if (isset($posted_list["comments"]))
{
	echo " value='".$posted_list["comments"]."' ";
}
echo "</td>";
echo "</tr><tr><td></td><td>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td valign='top' align='right'></td>";
echo "<td>";
echo "<input type='checkbox' name='flag_public' CHECKED";
echo " value='1' >Ja, dit contact mag zichtbaar zijn voor iedereen";

echo "</td>";
echo "</tr><tr><td></td><td>";
echo "</td>";
echo "</tr>";

echo "<tr><td></td><td><input type='submit' name='zend' value='Opslaan'>";
echo "</td></tr>";
echo "</table></form></div>";

include($rootpath."includes/inc_footer.php");

////////////////////////

function validate_input($posted_list)
{
  	global $db;
	$error_list = array();
	if (empty($posted_list["value"]) || (trim($posted_list["value"]) == ""))
	{
		$error_list["value"] = "<font color='#F56DB5'>Vul <strong>waarde</strong> in!</font>";
	}

	if(!$db->GetOne('SELECT abbrev FROM type_contact WHERE  id = '.$posted_list["id_type_contact"]))
	{
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}
	return $error_list;
}

