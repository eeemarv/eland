<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

if (isset($_POST["zend"]))
{
	$tc = array();
	$tc['name'] = $_POST['name'];
	$tc['abbrev'] = $_POST['abbrev'];
	
	$error = (empty($tc['name'])) ? 'Geen naam ingevuld! ' : '';
	$error .= (empty($tc['abbrev'])) ? 'Geen afkorting ingevuld! ' : $error;

	if (!$error)
	{
		if ($db->AutoExecute('type_contact', $tc, 'INSERT'))
		{
			$alert->success('Contact type toegevoegd.');
		}
		else
		{
			$alert->error('Fout bij het opslaan');
		}

		header('Location: ' . $rootpath . 'type_contact/overview.php');
		exit;
	}

	$alert->error('Corrigeer één of meerdere velden.');
}

$h1 = 'Contact type toevoegen';

include $rootpath . 'includes/inc_header.php';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="name" name="name" maxlength="20" ';
echo 'value="' . $ct['name'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="abbrev" class="col-sm-2 control-label">Afkorting</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="abbrev" name="abbrev" maxlength="11" ';
echo 'value="'. $ct['abbrev'] . '" required>';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'type_contact/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-success">';

echo '</form>';

include $rootpath . 'includes/inc_footer.php';

