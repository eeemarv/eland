<?php

$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$elas_mongo->connect();

$setting = ($_GET['edit']) ?: false;

if ($setting)
{
	$eh_config = $elas_heroku_config[$setting];

	if ($_POST['zend'])
	{
		$value = $_POST['value'];

		if ($value != '')
		{
			if (writeconfig($setting, $value))
			{
				$alert->success('Instelling aangepast.');
				cancel();
			}
		}
		$alert->error('Instelling niet aangepast.');
	}
	else
	{
		$value = readconfigfromdb($setting);
	}

	$description = ($eh_config[1]) ?: $db->fetchColumn('select description from config where setting = ?', array($setting));

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

	echo aphp('config', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

// exclude plaza stuff, emptypasswordlogin, share_enabled, pwscore
$config = $db->fetchAll('select *
	from config
	where category not like \'plaza%\'
		and setting <> \'emptypasswordlogin\'
		and setting <> \'share_enabled\'
		and setting <> \'pwscore\'
		and setting <> \'msgexpwarningdays\'
		and setting <> \'news_announce\'
		and setting <> \'mailinglists_enabled\'
		and setting <> \'from_address_transactions\'
	order by category, setting');

$eh_settings = array_keys($elas_heroku_config);

$cursor = $elas_mongo->settings->find(array('name' => array('$in' => $eh_settings))); 

$eh_stored_settings = array();

foreach ($cursor as $c)
{
	$eh_stored_settings[$c['name']] = $c['value'];
}

foreach ($eh_settings as $setting)
{
	$default = (isset($eh_stored_settings[$setting])) ? false : true;

	$config[] = array(
		'category'		=> 'eLAS-Heroku',
		'setting'		=> $setting,
		'value'			=> ($default) ? $elas_heroku_config[$setting][0] : $eh_stored_settings[$setting],
		'description'	=> $elas_heroku_config[$setting][1],
		'default'		=> $default,
	);
}


$h1 = 'Instellingen';
$fa = 'gears';

include $rootpath . 'includes/inc_header.php';

echo 'Tijdzone: UTC' . date('O') . '</p>';

echo '<div class="panel panel-default printview">';

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
	echo ($c['default']) ? ' class="danger"' : '';
	echo '>';
	echo '<td>' . $c['category'] . '</td>';
	echo '<td>';
	echo aphp('config', 'edit=' . $c['setting'], $c['setting']);
	echo '</td>';
	echo '<td>' . $c['value'] . '</td>';
	echo '<td>' . $c['description'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

echo '<p>Waardes in het rood moeten nog gewijzigd (of bevestigd) worden</p>';

include $rootpath . 'includes/inc_footer.php';

function cancel()
{
	header('Location: ' . generate_url('config'));
	exit;
}
