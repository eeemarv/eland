<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_form.php");

$uid = $_GET['uid'];
$cid = $_GET['cid'];

if(!isset($cid))
{
	header("Location: view.php?id=$uid");	
}

if(isset($_POST["zend"])){
	$contact = array();
	$contact["id_type_contact"] = $_POST["id_type_contact"];
	$contact["value"] = $_POST["value"];
	$contact["flag_public"] = ($_POST["flag_public"]) ? 1 : 0;
	$contact["comments"] = $_POST["comments"];
	$error_list = validate_input($contact);
	
	if(empty($error_list)){
		if ($db->update('contact', $contact, array('id' => $cid)))
		{
			$alert->success('Contact aangepast.');
			header('Location: view.php?id=' . $uid);
			exit;
		}
	}

	$alert->error('Contact niet aangepast.');
}
else
{
	$contact = $db->fetchAssoc('SELECT * FROM contact WHERE id = ?', array($cid));
}

$tc = $db->fetchAll('SELECT id, name FROM type_contact');
assoc($tc);
$user = readuser($uid);

$h1 = 'Contact aanpassen';
	
include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<p>Gebruiker: ' . $user['letscode'] . ' ' . $user['fullname'] . '</p>';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="id_type_contact" class="col-sm-2 control-label">Type</label>';
echo '<div class="col-sm-10">';
echo '<select name="id_type_contact" id="id_type_contact" class="form-control" required>';
render_select_options($tc, $contact['id_type_contact']);
echo "</select>";
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="value" class="col-sm-2 control-label">Waarde</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="value" name="value" ';
echo 'value="' . $contact['value'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="comments" class="col-sm-2 control-label">Commentaar</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="comments" name="comments" ';
echo 'value="' . $contact['comments'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="flag_public" class="col-sm-2 control-label">';
echo 'Ja, dit contact mag zichtbaar zijn voor iedereen</label>';
echo '<div class="col-sm-10">';
echo '<input type="checkbox" name="flag_public" id="flag_public"';
if ($contact['flag_public'])
{
	echo ' checked="checked"';
}
echo '>';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'users/view.php?id=' . $uid . '" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

////////////////

function validate_input($contact)
{
	global $db;
	$error_list = array();
	if (empty($contact["value"]) || (trim($contact["value"]) == ""))
	{
		$error_list[] = 'Vul waarde in!';
	}

	if (!$db->fetchColumn('select id from type_contact where id = ?', array($contact['id_type_contact'])))
	{
		$error_list[]= 'Contact type bestaat niet!';
	}

	return $error_list;
}


