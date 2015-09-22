<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$uid = $_GET['uid'];

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
		if ($db->insert('contact', $contact))
		{
			$alert->success('Contact opgeslagen.');
			header("Location: view.php?id=$uid");
			exit;
		}
	}

	$alert->error('Contact niet opgeslagen.');
}

$tc = array();

$rs = $db->fetchAll('SELECT id, name FROM type_contact');

$rs->execute();

while ($row = $rs->fetch())
{
	$tc[$row['id']] = $row['name'];
}

$user = readuser($uid);

$h1 = 'Contact toevoegen';

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
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-success">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';


////////////////////

function validate_input($contact)
{
	global $db;

	$error_list = array();

	if (empty($contact["value"]) || (trim($contact["value"]) == ""))
	{
		$error_list["value"] = "<font color='#F56DB5'>Vul <strong>waarde</strong> in!</font>";
	}

	if(!$db->fetchColumn('SELECT abbrev FROM type_contact WHERE id = ?', array($contact['id_type_contact'])))
	{
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}
	return $error_list;
}

