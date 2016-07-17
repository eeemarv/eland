<?php

$rootpath = './';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$setting = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$submit = (isset($_POST['zend'])) ? true : false;

$eland_config_explain = [
	'users_can_edit_username'	=> 'Gebruikers kunnen zelf hun gebruikersnaam aanpassen [0, 1]',
	'users_can_edit_fullname'	=> 'Gebruikers kunnen zelf hun volledige naam (voornaam + achternaam) aanpassen [0, 1]',
	'registration_en'			=> 'Inschrijvingsformulier ingeschakeld [0, 1]',
	'forum_en'					=> 'Forum ingeschakeld [0, 1]',
	'css'						=> 'Extra stijl: url van .css bestand (Vul 0 in wanneer niet gebruikt)',
	'msgs_days_default'			=> 'Standaard geldigheidsduur in aantal dagen van vraag en aanbod.',
	'balance_equilibrium'		=> 'Het uitstapsaldo voor actieve leden. Het saldo van leden met status uitstapper kan enkel bewegen in de richting van deze instelling.',
	'date_format'				=> 'Datumformaat',
];

if ($setting)
{
	$eh_config = isset($eland_config_default[$setting]) ? $eland_config_default[$setting] : false;

	if ($submit)
	{
		$value = $_POST['value'];

		if (strlen($value) > 60)
		{
			$errors[] = 'De waarde mag maximaal 60 tekens lang zijn.';
		}

		if ($value == '')
		{
			$errors[] = 'De waarde mag niet leeg zijn.';
		}

		if ($error_token = get_error_form_token())
		{
			$errors[] = $error_token;
		}

		if (!count($errors))
		{
			$exdb->set('setting', $setting, ['value' => $value]);

			if (!$eland_config[$setting])			
			{
				if (!$db->update('config', array('value' => $value, '"default"' => 'f'), array('setting' => $setting)))
				{
					return false;
				}
			}

			$redis_key = $schema . '_config_' . $setting;
			$redis->set($redis_key, $value);
			$redis->expire($redis_key, 2592000);

			$alert->success('Instelling aangepast.');
			cancel();
		}

		$alert->error($errors);
	}
	else
	{
		$value = readconfigfromdb($setting);
	}

	$description = ($eland_config_explain[$setting]) ? $eland_config_explain[$setting] : $db->fetchColumn('select description from config where setting = ?', array($setting));

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
	echo 'value="' . $value . '" required maxlength="60">';
	echo '</div>';
	echo '</div>';

	echo aphp('config', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';
	generate_form_token();

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
		and setting <> \'from_address\'
		and setting <> \'from_address_transactions\'
		and setting <> \'ets_enabled\'
	order by category, setting');

foreach ($config as $c)
{
	$config[$c['setting']] = $c['value'];
}

foreach ($eland_config as $setting => $default)
{
	unset($value);

	$row = $exdb->get('setting', $setting);

	if ($row)
	{
		$value = $row['data']['value'];
	}

	$config[] = array(
		'category'		=> 'eLAND',
		'setting'		=> $setting,
		'value'			=> isset($value) ? $value : $default[0],
		'description'	=> $default[1],
		'default'		=> isset($value) ? false : true,
	);

	$config[$setting] = $value;
}


$h1 = 'Instellingen';
$fa = 'gears';

include $rootpath . 'includes/inc_header.php';

$tab_panes = [
	'systemname'	=> [
			'lbl'	=> 'Groepsnaam',
			'inputs' => [
				'systemname' => [
						'lbl'	=> 'Groepsnaam',
						'type'	=> 'text',
					],
				'systemtag' => [
						'lbl'	=> 'Tag (hoofding voor emails)',
						'type'	=> 'text',
						'attr'	=> [
							'maxlength' => 30,
						]
					],
				],
		],
	'currency'		=> [
			'lbl'	=> 'LETS-Eenheid',
		],
	'limits'		=> [
			'lbl'		=> 'Limieten',
			'pane-lbl' => 'Standaardwaarden limieten van nieuwe gebruikers',
		],
	'mailaddresses'	=> [
			'lbl'	=> 'Mailadressen',
		],
	'msgexp'		=> [
			'lbl'	=> 'Vervallen vraag en aanbod',
		],
];

$tab_active = 'systemname';

echo '<div>';
echo '<ul class="nav nav-tabs" role="tablist">';

foreach ($tab_panes as $id => $pane)
{	
	echo '<li role="presentation"';
	echo ($id == $tab_active) ? ' class="active"' : '';
	echo '>';
	echo '<a href="#' . $id . '" aria-controls="' . $id . '" role="tab" data-toggle="tab">';
	echo $pane['lbl'];
	echo '</a>';
	echo '</li>';
}

echo '</ul>';

echo '<div class="tab-content">';

/**
 *
 */

foreach ($tab_panes as $id => $pane)
{
	echo '<div role="tabpanel" class="tab-pane active" id="' . $id . '">';

	echo '<form mathod="post" class="form form-horizontal">';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading"><h4>';
	echo (isset($pane['lbl_pane'])) ? $pane['lbl_pane'] : $pane['lbl'];
	echo '</h4></div>';

	echo '<ul class="list-group">';

	foreach ($pane['inputs'] as $name => $input)
	{
		echo '<li class="list-group-item">';
		echo '<div class="form-group">';
		echo '<label for="fixed" class="col-sm-3 control-label">';
		echo $input['lbl'];
		echo '</label>';
		echo '<div class="col-sm-9">';
		echo '<input type="';
		echo (isset($input['type'])) ? $input['type'] : 'text';
		echo '" class="form-control" ';
		echo 'name="' . $name . '" value="' . $config[$name] . '"';
		echo (isset($input['attr']['maxlength'])) ? '' : ' maxlength="60"';
		echo (isset($input['attr']['minlength'])) ? '' : ' minlength="1"';
		echo '>';
		echo '</div>';
		echo '</div>';

		echo '</li>';
	}

	echo '</ul>';

	echo '<div class="panel-footer">';
	echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="' . $id . '_submit">';
	echo '</div>';

	echo '</div>';

	echo '</form>';

	echo '</div>';
}


/**
 * systemname
 */
/*
echo '<div role="tabpanel" class="tab-pane active" id="systemname">';

echo '<form mathod="post" class="form form-horizontal">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Groepsnaam</h4></div>';

echo '<ul class="list-group"><li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Naam van de groep</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" class="form-control" ';
echo 'name="systemname" value="' . $config['systemname'] . '" maxlength="60" minlength="3">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '<li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Tag (voor email-hoofdings)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" class="form-control" ';
echo 'name="systemtag" value="' . $config['systemtag'] . '" maxlength="60" minlength="1">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="systemnamesubmit">';
echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';
*/
/*
 * currency
 */
/*
echo '<div role="tabpanel" class="tab-pane" id="currency">';

echo '<form mathod="post" class="form form-horizontal">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>LETS-eenheid</h4></div>';

echo '<ul class="list-group"><li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">LETS-eenheid (meervoud)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" class="form-control" ';
echo 'name="currency" value="' . $config['currency'] . '" maxlength="60" minlength="1">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '<li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Aantal per uur</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" class="form-control" ';
echo 'name="currencyratio" value="' . $config['currencyratio'] . '" max="240" min="1">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="currencysubmit">';
echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';
*/
/*
 * mail adresses
 */
/*
echo '<div role="tabpanel" class="tab-pane" id="mailaddresses">';

echo '<form mathod="post" class="form form-horizontal">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Mailadressen</h4></div>';

echo '<ul class="list-group"><li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Algemeen admin/beheerder</label>';
echo '<div class="col-sm-9">';
echo '<input type="email" class="form-control" ';
echo 'name="admin" value="' . $config['admin'] . '" maxlength="60" minlength="6">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '<li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Support/helpdesk</label>';
echo '<div class="col-sm-9">';
echo '<input type="email" class="form-control" ';
echo 'name="support" value="' . $config['support'] . '" maxlength="60" minlength="6">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '<li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Nieuwsbeheerder</label>';
echo '<div class="col-sm-9">';
echo '<input type="email" class="form-control" ';
echo 'name="newsadmin" value="' . $config['newsadmin'] . '" maxlength="60" minlength="6">';
echo '</div>';
echo '</div>';

echo '</li>';


echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="mailaddressessubmit">';
echo '</div>';

echo '</div>';

echo '</form>'; 

echo '</div>';
*/
/**
 * expired messages
 */
/*
echo '<div role="tabpanel" class="tab-pane" id="msgexp">';

echo '<form mathod="post">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Vervallen vraag en aanbod</h4></div>';

echo '<ul class="list-group">';

echo '<li class="list-group-item">';

echo '<input type="checkbox" name="msgcleanupenabled" value="1"';
echo ($config['msgcleanupenabled']) ? ' checked="checked"' : '';
echo '>';
echo '&nbsp;&nbsp;Ruim vervallen vraag en aanbod op na ';
echo '<input type="number" value="' . $config['msgexpcleanupdays'] . '" ';
echo 'name="msgcleanupdays" min="1" max="365" class="sm-size">';
echo ' dagen';

echo '</li>';

echo '<li class="list-group-item">';

echo '<input type="checkbox" name="msgexpwarnenabled" value="1"';
echo $config['msgexpwarnenabled'] ? ' checked="checked"' : '';
echo '>';
echo '&nbsp;&nbsp;Mail een notificatie naar de eigenaar van een ';
echo 'vraag of aanbod bericht op het moment dat het vervalt.';

echo '</li>';

echo '<li class="list-group-item">';

echo '<input type="checkbox" name="adminmsgexp" value="1"';
echo $config['adminmsgexp'] ? ' checked="checked"' : '';
echo '>';
echo '&nbsp;&nbsp;Mail de admin een overzicht van vervallen vraag en aanbod elke ';
echo '<input type="number" value="' . $config['adminmsgexpfreqdays'] . '" ';
echo 'name="adminmsgexpfreqdays" max="365" min="1" class="sm-size">';
echo ' dagen';

echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="msgexp">';
echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';
*/
/**
 * maintenance mode
 */
/*
echo '<div role="tabpanel" class="tab-pane" id="maintenance">';

echo '<form mathod="post">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Onderhoudsmodus</h4></div>';

echo '<ul class="list-group">';

echo '<li class="list-group-item">';

echo '<input type="checkbox" value="1" name="maintenance"';
echo ($config['maintenance']) ? ' checked="checked"' : '';
echo '>';
echo '&nbsp;&nbsp;Onderhoudsmodus aan: alleen admins kunnen inloggen.';

echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="msgexp">';
echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';
*/
/**
 * mail enable
 */
/*
echo '<div role="tabpanel" class="tab-pane" id="mailenabled">';

echo '<form mathod="post">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Mail functionaliteit</h4></div>';

echo '<ul class="list-group">';

echo '<li class="list-group-item">';

echo '<input type="checkbox" value="1" name="mailenabled"';
echo ($config['mailenabled']) ? ' checked="checked"' : '';
echo '>';
echo '&nbsp;&nbsp;Mail functionaliteit aan: het systeem verstuurt emails.';

echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="msgexp">';
echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';
*/
/**
 * saldomail
 */

echo '<div role="tabpanel" class="tab-pane" id="saldomail">';

echo '<form mathod="post">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Overzichtsmail met recent vraag en aanbod</h4></div>';

echo '<ul class="list-group">';

echo '<li class="list-group-item">';

echo 'Verstuur de overzichtsmail met recent vraag en aanbod om de ';
echo '<input type="number" value="' . $config['saldofreqdays'] . '" class="sm-size" name="saldofreqdays">';
echo ' dagen';

echo '<p><small>Noot: Gebruikers kunnen steeds ontvangst van de overzichtsmail aan- of afzetten ';
echo 'in hun profielinstellingen.</small></p>';
echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="saldomailsubmit">';
echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';

/*
 * new members
 */

echo '<form mathod="post" class="form form-horizontal">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Instappers</h4></div>';

echo '<ul class="list-group"><li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Aantal dagen dat een nieuw lid als instapper getoond wordt</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" class="form-control" ';
echo 'name="newuserdays" value="' . $config['newuserdays'] . '" max="365" min="0">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="newuserdayssubmit">';
echo '</div>';

echo '</div>';

echo '</form>'; 

/*
 * standard values for limits
 */

echo '<div role="tabpanel" class="tab-pane" id="limits">';

echo '<form mathod="post" class="form form-horizontal">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Standaardwaarden limieten van nieuwe gebruikers</h4></div>';

echo '<ul class="list-group">';

echo '<li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Minimum limiet</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" class="form-control" ';
echo 'name="minlimit" value="' . $config['minlimit'] . '">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '<li class="list-group-item">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Maximum limiet</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" class="form-control" ';
echo 'name="maxlimit" value="' . $config['maxlimit'] . '">';
echo '</div>';
echo '</div>';

echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="limitssubmit">';
echo '</div>';

echo '</div>';

echo '</form>'; 

echo '</div>';

/**
 *
 */

echo '<div role="tabpanel" class="tab-pane" id="date_format">';

echo '<form mathod="post" class="form form-horizontal">';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading"><h4>Weergave datums en tijd</h4></div>';

echo '<ul class="list-group">';

echo '<li class="list-group-item">';

echo '<div class="form-group">';
echo '<div class="col-sm-12">';

echo '<select class="form-control" name="date_format">';

render_select_options($date_format->get_options(), $config['date_format']);

echo '</select>';

echo '</div>';
echo '</div>';

echo '</li>';

echo '</ul>';

echo '<div class="panel-footer">';
echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="dateformatsubmit">';
echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';

/**/

echo '</div>';
echo '</div>';



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
	echo aphp('config', ['edit' => $c['setting']], $c['setting']);
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
