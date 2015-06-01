<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';
require_once $rootpath . 'includes/inc_form.php';

$tc = $db->GetAssoc('SELECT id, name FROM type_contact');

if(isset($_POST['zend']))
{
	$contact = array();
	$contact['id_type_contact'] = $_POST['id_type_contact'];
	$contact['value'] = $_POST['value'];
	$contact['comments'] = $_POST['comments'];
	$contact['flag_public'] = ($_POST['flag_public']) ? 1 : 0;

	$error = (!$contact['value']) ? 'Geen waarde ingevuld! ' : '';
	$error = (!$tc[$contact['id_type_contact']]) ? 'Dit contact type bestaat niet! ' : $error;

	if(!$error)
	{
		$contact['id_user'] = $s_id;
		if ($db->AutoExecute('contact', $contact, 'INSERT'))
		{
			$alert->success('Contact toegevoegd.');
		}
		else
		{
			$alert->error('Opslaan contact niet gelukt.');
		}
		header('Location: ' . $rootpath . 'userdetails/mydetails.php');
		exit;
	}

	$alert->error('Fout in één of meerdere velden. ' . $error);
}

$h1 = 'Contact toevoegen';

include $rootpath . 'includes/inc_header.php';

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
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-success">';

echo '</form>';

include $rootpath . 'includes/inc_footer.php';

