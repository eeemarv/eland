<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$id = $_GET['id'];

if(!isset($id))
{
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

$tc_prefetch = $db->GetRow('select * from type_contact where id = ' . $id);

if (in_array($tc_prefetch['abbrev'], array('mail', 'tel', 'gsm', 'adr', 'web')))
{
	$alert->warning('Beschermd contact type.');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;	
}

if(isset($_POST['zend']))
{
	$tc = array();
	$tc['name'] = $_POST['name'];
	$tc['abbrev'] = $_POST['abbrev'];
	$tc['protect'] = ($_POST['protect']) ? true : false;
	$tc['id'] = $_GET['id'];

	$error = (empty($tc['name'])) ? 'Geen naam ingevuld! ' : '';
	$error .= (empty($tc['abbrev'])) ? 'Geen afkorting ingevuld! ' : $error;

	$tc['mdate'] = date('Y-m-d H:i:s');

	if (!$error)
	{
		if ($db->AutoExecute('type_contact', $tc, 'UPDATE', 'id=' . $id))
		{
			$alert->success('Contact type aangepast.');
			header('Location: ' . $rootpath . 'type_contact/overview.php');
			exit;
		}
		else
		{
			$alert->error('Fout bij het opslaan.');
		}
	}
	else
	{
		$alert->error('Fout in één of meer velden. ' . $error);
	}
}
else
{
	$tc = $tc_prefetch;
}

$h1 = 'Contact type aanpassen';
$fa = 'circle-o-notch';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';
echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="name" name="name" maxlength="20" ';
echo 'value="' . $tc['name'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="abbrev" class="col-sm-2 control-label">Afkorting</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="abbrev" name="abbrev" maxlength="11" ';
echo 'value="'. $tc['abbrev'] . '" required>';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'type_contact/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';

echo '</form>';
echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
