<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_form.php';

$id = $_GET['id'];

if(!isset($id))
{
	header('Location: ' . $rootpath . 'userdetails/mydetails.php');
	exit;
}

$contact = $db->GetRow('SELECT * FROM contact WHERE id=' . $id);

if ($contact['id_user'] != $s_id)
{
	$alert->error('Je hebt hebt geen rechten om dit contact aan te passen.');
	header('Location: ' . $rootpath . 'userdetails/mydetails.php');
	exit;
}

if(isset($_POST["zend"]))
{
	$posted_list = array();
	$posted_list["id_type_contact"] = $_POST["id_type_contact"];
	$posted_list["value"] = $_POST["value"];
	if (trim($_POST["flag_public"]) == 1)
	{
		$posted_list["flag_public"] = 1;
	}
	else
	{
		$posted_list["flag_public"] = 0;
	}
	$posted_list["comments"] = $_POST["comments"];
	$posted_list["id"] = $_GET["id"];

	$error_list = validate_input($posted_list);

	if(empty($error_list))
	{
		$result = $db->AutoExecute("contact", $posted_list, 'UPDATE', 'id='.$posted_list["id"]);
		$alert->success('Contact aangepast.');
		header('Location: '. $rootpath . 'userdetails/mydetails.php');
		exit;
	}
	else
	{
		$alert->error('Eén of meerdere velden zijn niet correct ingevuld.');
	}
}

$tc = $db->GetAssoc('SELECT id, name FROM type_contact');

$h1 = 'Contact aanpassen';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

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

echo '<a href="' . $rootpath . 'userdetails/mydetails.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';


function validate_input($posted_list)
{
    global $db;

	$error_list = array();

	if (empty($posted_list["value"]) || (trim($posted_list["value"]) == ""))
	{
		$error_list["value"] = 'Vul waarde in!';
	}

    if (!$db->GetOne('select id from type_contact where id = ' . $posted_list['id_type_contact']))
	{
		$error_list[]= 'Contacttype bestaat niet!';
	}

	return $error_list;
}
