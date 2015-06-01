<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userfunctions.php");
require_once($rootpath."includes/inc_form.php");

$uid = $_GET["uid"];

if(isset($_POST["zend"]))
{
	$contact = array();
	$contact["id_type_contact"] = $_POST["id_type_contact"];
	$contact["value"] = $_POST["value"];
	$contact["comments"] = $_POST["comments"];
	$contact["flag_public"] = ($_POST["flag_public"]) ? 1 : 0;
	$contact['id_user'] = $uid;

	$error_list = validate_input($contact);
	
	if(empty($error_list))
	{
		if ($db->AutoExecute('contact', $contact, 'INSERT'))
		{
			$alert->success('Contact opgeslagen.');
			header("Location: view.php?id=$uid");
			exit;
		}
	}

	$alert->error('Contact niet opgeslagen.');
}


$contact_types = $db->GetAssoc('SELECT id, name FROM type_contact');
$user = $db->GetRow('SELECT name, letscode FROM users WHERE id = ' . $uid);

include($rootpath."includes/inc_header.php");
echo "<h1>Contact toevoegen</h1>";

echo "<div class='border_b'>";
echo "<form method='POST'>";
echo "<table  class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr>";
echo "<td valign='top' align='right'>Type</td>";
echo "<td>";
echo '<select name="id_type_contact"';
render_select_options($contact_types, $contact['id_type_contact']);
echo "</select>";
echo "</td></tr><tr><td></td>";
echo "<td>";
if(isset($error_list["id_type_contact"]))
{
	echo $error_list["id_type_contact"];
}
echo "</td>";
echo "</tr>";
echo "<tr>";
echo "<td valign='top'  align='right'>Waarde</td>";
echo "<td valign='top' ><input type='text' name='value' size='30' required ";
if (isset($contact["value"]))
{
	echo " value='".$contact["value"]."' ";
}
echo "></td></tr><tr><td></td>";
echo "<td valign='top'>";
if(isset($error_list["value"]))
{
	echo $error_list["value"];
}
echo "</td>";
echo "</tr>";
echo "<tr>";
echo "<td valign='top'  align='right'>Commentaar</td>";
echo "<td valign='top'>";
echo "<input type='text' name='comments' size='30' ";
if (isset($contact["comments"])){
	echo " value='".$contact["comments"]."' ";
}
echo "</td></tr><tr><td></td>";
echo "<td></td>";
echo "</tr>";

echo "<tr>";
echo "<td valign='top' align='right'></td>";
echo "<td>";
echo "<input type='checkbox' name='flag_public' ";
if ($contact["flag_public"])
{
	echo ' checked="checked" ';
}

echo " value='1' >Ja, dit contact mag zichtbaar zijn voor iedereen";

echo '<p>Gebruiker: ' . $user['name'] . ' ( ' . $user['letscode'] . ' )</p>';

echo "<tr><td></td><td><input type='submit' name='zend' value='Opslaan'>";
echo "</td></tr>";
echo "</table></form></div>";


include($rootpath."includes/inc_footer.php");


////////////////////

function validate_input($contact)
{
	global $db;

	$error_list = array();

	if (empty($contact["value"]) || (trim($contact["value"]) == ""))
	{
		$error_list["value"] = "<font color='#F56DB5'>Vul <strong>waarde</strong> in!</font>";
	}

	if(!$db->GetOne('SELECT abbrev FROM type_contact WHERE id = ' . $contact['id_type_contact']))
	{
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}
	return $error_list;
}

