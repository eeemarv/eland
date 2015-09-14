<?php
ob_start();
$rootpath = '../';
$role='admin';
require_once $rootpath . 'includes/inc_default.php';

$setting = $_GET['setting'];

if ($_POST['zend'])
{
	$value = $_POST['value'];
	if ($value != '')
	{
		if (writeconfig($setting, $value))
		{
			$alert->success('Instelling aangepast.');
			header('Location: ' . $rootpath . 'preferences/config.php');
			exit;
		}
	}
	$alert->error('Instelling niet aangepast.');
}
else
{
	$value = readconfigfromdb($setting);
}

$description = $db->fetchColumn('select description from config where setting = ?', array($setting));

$h1 = 'Instelling ' . $setting . ' aanpassen';
$fa = 'gears';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<p>' . $description . '</p>';

echo '<div class="form-group">';
echo '<label for="setting" class="col-sm-2 control-label">Instelling</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="setting" name="setting" ';
echo 'value="' . $setting . '" required readonly>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="value" class="col-sm-2 control-label">Waarde</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="value" name="value" ';
echo 'value="' . $value . '" required>';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'preferences/config.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';
echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
