<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_form.php';

$mode = $_GET['mode'];
$id = $_GET['id'];

$group = $_POST;

if ($mode == 'edit' && !$id)
{
	header('Location: ' . $rootpath . 'interlets/overview.php');
	exit;
}

if ($_POST['zend'])
{
	if ($mode == 'edit')
	{
		if ($db->AutoExecute('letsgroups', $group, 'UPDATE', 'id=' . $id))
		{
			$alert->success('Letsgroep aangepast.');
			header('Location: overview.php');
			exit;
		}

		$alert->error('Letsgroep niet aangepast.');
	}
	else
	{
		if ($db->AutoExecute('letsgroups', $group, 'INSERT'))
		{
			$alert->success('Letsgroep opgeslagen.');
			header('Location: overview.php');
			exit;
		}

		$alert->error('Letsgroep niet opgeslagen.');
	}
}
else if ($mode == 'edit')
{
	$group = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($id));
}

$h1 = 'LETS groep ';
$h1 .= ($mode == 'edit') ? 'aanpassen' : 'toevoegen';
$fa = 'share-alt';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="groupname" class="col-sm-2 control-label">Groepsnaam</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="groupname" name="groupname" ';
echo 'value="' . $group['groupname'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="shortname" class="col-sm-2 control-label">Korte naam ';
echo '<small><i>(kleine letters zonder spaties)</i></small></label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="shortname" name="shortname" ';
echo 'value="' . $group['shortname'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="prefix" class="col-sm-2 control-label">Prefix ';
echo '<small><i>(kleine letters zonder spaties)</i></small></label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="prefix" name="prefix" ';
echo 'value="' . $group['prefix'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="apimethod" class="col-sm-2 control-label">';
echo 'API methode <small><i>(type connectie naar de andere installatie)</i></small></label>';
echo '<div class="col-sm-10">';
echo '<select class="form-control" id="apimethod" name="apimethod" >';
render_select_options(array(
	'elassoap'	=> 'eLAS naar eLAS (elassoap)',
	'internal'	=> 'Intern (eigen installatie)',
	'mail'		=> 'E-mail',
), $group['apimethod']);
echo '</select>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="remoteapikey" class="col-sm-2 control-label">Remote API key</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="remoteapikey" name="remoteapikey" ';
echo 'value="' . $group['remoteapikey'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="localletscode" class="col-sm-2 control-label">';
echo 'Lokale letscode <small><i>(de letscode waarmee de andere ';
echo 'groep op deze installatie bekend is.)</i></small></label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="localletscode" name="localletscode" ';
echo 'value="' . $group['localletscode'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="myremoteletscode" class="col-sm-2 control-label">';
echo 'Remote LETS code <small><i>(De letscode waarmee deze groep bij de andere bekend is)';
echo '</i></small></label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="myremoteletscode" name="myremoteletscode" ';
echo 'value="' . $group['myremoteletscode'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="url" class="col-sm-2 control-label">';
echo 'URL';
echo '</label>';
echo '<div class="col-sm-10">';
echo '<input type="url" class="form-control" id="url" name="url" ';
echo 'value="' . $group['url'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="elassoapurl" class="col-sm-2 control-label">';
echo 'SOAP URL <small><i>(voor eLAS, de URL met /soap erachter)</i></small>';
echo '</label>';
echo '<div class="col-sm-10">';
echo '<input type="url" class="form-control" id="elassoapurl" name="elassoapurl" ';
echo 'value="' . $group['elassoapurl'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="presharedkey" class="col-sm-2 control-label">';
echo 'Preshared key';
echo '</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="presharedkey" name="presharedkey" ';
echo 'value="' . $group['presharedkey'] . '">';
echo '</div>';
echo '</div>';

$btn = ($mode == 'edit') ? 'primary' : 'success';
echo '<a href="' . $rootpath . 'interlets/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
