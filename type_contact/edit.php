<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$id = $_GET['id'];

if(!isset($id))
{
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

$ct_prefetch = $db->GetRow('select * from type_contact where id = ' . $id);

if (in_array($ct_prefetch['abbrev'], array('mail', 'tel', 'gsm', 'adr', 'web')))
{
	$alert->warning('Beschermd contact type.');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;	
}

if(isset($_POST['zend']))
{
	$ct = array();
	$ct['name'] = $_POST['name'];
	$ct['abbrev'] = $_POST['abbrev'];
	$ct['protect'] = ($_POST['protect']) ? true : false;
	$ct['id'] = $_GET['id'];

	$error = (empty($ct['name'])) ? 'Geen naam ingevuld! ' : '';
	$error .= (empty($ct['abbrev'])) ? 'Geen afkorting ingevuld! ' : $error;

	
	$ct['mdate'] = date('Y-m-d H:i:s');

	if (!$error)
	{
		if ($db->AutoExecute('type_contact', $ct, 'UPDATE', 'id=' . $id))
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
	$ct = $ct_prefetch;
}

$h1 = 'Contact type aanpassen';

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
echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';

echo '</form>';

include $rootpath . 'includes/inc_footer.php';
