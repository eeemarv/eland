<?php
ob_start();
$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$setting = ($_GET['edit']) ?: false;

if ($setting)
{
	if ($_POST['zend'])
	{
		$value = $_POST['value'];

		if ($value != '')
		{
			if (writeconfig($setting, $value))
			{
				$alert->success('Instelling aangepast.');
				header('Location: ' . $rootpath . 'config.php');
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

	echo '<a href="' . $rootpath . 'config.php" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

// exclude plaza stuff
$config = $db->fetchAll('SELECT * FROM config where category not like \'plaza%\' ORDER BY category, setting');

$h1 = 'Instellingen';
$fa = 'gears';

include $rootpath . 'includes/inc_header.php';

echo 'Tijdzone: UTC' . date('O') . '</p>';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo '<th>Categorie</th>';
echo '<th>Instelling</th>';
echo '<th>Waarde</th>';
echo '<th data-hide="phone">Omschrijving</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($config as $c)
{
	echo '<tr';
	echo ($c['default'] == 't') ? ' class="bg-danger"' : '';
	echo '>';
	echo '<td>' . $c['category'] . '</td>';
	echo '<td>';
	echo '<a href="' . $rootpath . 'config.php?edit=' . $c['setting'] . '">';
	echo  $c['setting'] . '</a></td>';
	echo '<td>' . $c['value'] . '</td>';
	echo '<td>' . $c['description'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '<p>Waardes in het rood moeten nog gewijzigd (of bevestigd) worden</p>';

echo '</div></div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
