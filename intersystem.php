<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

$id = $_GET['id'] ?? false;
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = isset($_GET['add']) ? true : false;
$add_schema = $_GET['add_schema'] ?? false;

$submit = isset($_POST['zend']) ? true : false;

if ($id || $edit || $del)
{
	$id = ($id) ?: (($edit) ?: $del);

	$group = $app['db']->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', [$id]);

	if (!$group)
	{
		$app['alert']->error('Groep niet gevonden.');
		cancel();
	}
}

if (!$app['config']->get('template_lets'))
{
	redirect_default_page();
}

if (!$app['config']->get('interlets_en'))
{
	redirect_default_page();
}

/**
 *	add
 */
if ($add || $edit)
{
	if ($submit)
	{
		$group = $_POST;

		$group['elassoapurl'] = $group['url'] . '/soap';

		unset($group['zend'], $group['form_token']);

		if (strlen($group['groupname']) > 128)
		{
			$errors[] = 'De groepsnaam mag maximaal 128 tekens lang zijn.';
		}

		if (strlen($group['shortname']) > 50)
		{
			$errors[] = 'De korte naam mag maximaal 50 tekens lang zijn.';
		}

		if (strlen($group['prefix']) > 5)
		{
			$errors[] = 'Prefix mag maximaal 5 tekens lang zijn.';
		}

		if (strlen($group['remoteapikey']) > 80)
		{
			$errors[] = 'De Remote Apikey mag maximaal 80 tekens lang zijn.';
		}

		if (strlen($group['localletscode']) > 20)
		{
			$errors[] = 'De lokale code mag maximaal 20 tekens lang zijn.';
		}

		if (strlen($group['myremoteletscode']) > 20)
		{
			$errors[] = 'De remote code mag maximaal 20 tekens lang zijn.';
		}

		if (strlen($group['url']) > 256)
		{
			$errors[] = 'De url mag maximaal 256 tekens lang zijn.';
		}

		if (strlen($group['soapurl']) > 256)
		{
			$errors[] = 'De eLAS soap url mag maximaal 256 tekens lang zijn.';
		}

		if (strlen($group['presharedkey']) > 80)
		{
			$errors[] = 'De Preshared Key mag maximaal 80 tekens lang zijn.';
		}

		if ($error_token = $app['form_token']->get_error())
		{
			$errors[] = $error_token;
		}

		$shortname = str_replace(' ', '', $group['groupname']);
		$shortname = substr($shortname, 0, 50);
		$group['shortname'] = strtolower($shortname);

		if ($edit)
		{
			if ($app['db']->fetchColumn('select id
				from letsgroups
				where url = ?
					and id <> ?', [$group['url'], $edit]))
			{
				$errors[] = 'Er bestaat al een groep met deze url.';
			}

			if ($app['db']->fetchColumn('select id
				from letsgroups
				where localletscode = ?
					and id <> ?', [$group['localletscode'], $edit]))
			{
				$errors[] = 'Er bestaat al een groep met deze lokale code.';
			}

			if (!count($errors))
			{
				if ($app['db']->update('letsgroups', $group, ['id' => $id]))
				{
					$app['alert']->success('Groep aangepast.');

					$app['interlets_groups']->clear_cache($s_schema);

					cancel($edit);
				}

				$app['alert']->error('Groep niet aangepast.');
			}
		}
		else
		{
			if ($app['db']->fetchColumn('select id from letsgroups where url = ?', [$group['url']]))
			{
				$errors[] = 'Er bestaat al een groep met deze url.';
			}

			if ($app['db']->fetchColumn('select id from letsgroups where localletscode = ?', [$group['localletscode']]))
			{
				$errors[] = 'Er bestaat al een groep met deze lokale code.';
			}

			if (!count($errors))
			{
				if ($app['db']->insert('letsgroups', $group))
				{
					$app['alert']->success('Groep opgeslagen.');

					$id = $app['db']->lastInsertId('letsgroups_id_seq');

					$app['interlets_groups']->clear_cache($s_schema);

					cancel($id);
				}

				$app['alert']->error('Letsgroep niet opgeslagen.');
			}
		}

		if (count($errors))
		{
			$app['alert']->error($errors);
		}
	}

	if ($add)
	{
		$group = [
			'groupname' 		=> '',
			'apimethod'			=> 'elassoap',
			'remoteapikey'		=> '',
			'localletscode'		=> '',
			'myremoteletscode'	=> '',
			'url'				=> '',
			'presharedkey'		=> '',
		];
	}

	if ($add_schema && $add)
	{
		if ($app['groups']->get_host($add_schema))
		{
			$group['url'] = $app['protocol'] . $app['groups']->get_host($add_schema);
			$group['groupname'] = $app['config']->get('systemname', $add_schema);
			$group['localletscode'] = $app['config']->get('systemtag', $add_schema);
		}
	}

	$h1 = 'LETS groep ';
	$h1 .= ($edit) ? 'aanpassen' : 'toevoegen';
	$fa = 'share-alt';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="groupname" class="col-sm-2 control-label">Groepsnaam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="groupname" name="groupname" ';
	echo 'value="' . $group['groupname'] . '" required maxlength="128">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="apimethod" class="col-sm-2 control-label">';
	echo 'API methode <small><i>(type connectie naar de andere installatie)</i></small></label>';
	echo '<div class="col-sm-10">';
	echo '<select class="form-control" id="apimethod" name="apimethod" >';

	echo get_select_options([
		'elassoap'	=> 'eLAND naar eLAND of eLAS (elassoap)',
		'internal'	=> 'Intern (eigen installatie - niet gebruiken)',
		'mail'		=> 'E-mail',
	], $group['apimethod']);

	echo '</select>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="remoteapikey" class="col-sm-2 control-label">Remote API key ';
	echo '<i><small>enkel voor eLAS</small></i></label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="remoteapikey" name="remoteapikey" ';
	echo 'value="' . $group['remoteapikey'] . '" maxlength="80">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="localletscode" class="col-sm-2 control-label">';
	echo 'Lokale letscode <small><i>(de letscode waarmee de andere ';
	echo 'groep op deze installatie bekend is.)</i></small></label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="localletscode" name="localletscode" ';
	echo 'value="' . $group['localletscode'] . '" maxlength="20">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="myremoteletscode" class="col-sm-2 control-label">';
	echo 'Remote LETS code <small><i>De letscode waarmee deze groep bij de andere bekend is, enkel voor eLAS';
	echo '</i></small></label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="myremoteletscode" name="myremoteletscode" ';
	echo 'value="' . $group['myremoteletscode'] . '" maxlength="20">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="url" class="col-sm-2 control-label">';
	echo 'URL (incluis http://)';
	echo '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="url" class="form-control" id="url" name="url" ';
	echo 'value="' . $group['url'] . '" maxlength="256">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="presharedkey" class="col-sm-2 control-label">';
	echo 'Preshared key, enkel voor eLAS';
	echo '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="presharedkey" name="presharedkey" ';
	echo 'value="' . $group['presharedkey'] . '" maxlength="80">';
	echo '</div>';
	echo '</div>';

	$btn = ($edit) ? 'primary' : 'success';
	$canc = ($edit) ? ['id' => $edit] : [];
	echo aphp('intersystem', $canc, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	echo get_schemas_groups();

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * delete
 */
if ($del)
{
	if ($submit)
	{

		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			cancel();
		}

		if($app['db']->delete('letsgroups', ['id' => $del]))
		{
			$app['alert']->success('Letsgroep verwijderd.');

			$app['interlets_groups']->clear_cache($s_schema);

			cancel();
		}

		$app['alert']->error('Letsgroep niet verwijderd.');
	}

	$h1 = 'Letsgroep verwijderen: ' . $group['groupname'];
	$fa = 'share-alt';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<p class="text-danger">Ben je zeker dat deze groep';
	echo ' moet verwijderd worden?</p>';
	echo '<div><p>';
	echo '<form method="post">';

	echo aphp('intersystem', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	echo $app['form_token']->get_hidden_input();

	echo "</form></p>";
	echo "</div>";

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * Show settings of a group
 */
if ($id)
{
	if (isset($group['url']))
	{
		$group['host'] = strtolower(parse_url($group['url'], PHP_URL_HOST));
	}

	if ($group['localletscode'] === '')
	{
		$user = false;
	}
	else
	{
		$user = $app['db']->fetchAssoc('select * from users where letscode = ?', [$group['localletscode']]);
	}

	$top_buttons .= aphp('intersystem', ['edit' => $id], 'Aanpassen', 'btn btn-primary', 'Letsgroep aanpassen', 'pencil', true);
	$top_buttons .= aphp('intersystem', ['del' => $id], 'Verwijderen', 'btn btn-danger', 'Letsgroep verwijderen', 'times', true);
	$top_buttons .= aphp('intersystem', [], 'Lijst', 'btn btn-default', 'Lijst letsgroepen', 'share-alt', true);

	$app['assets']->add('elas_soap_status.js');

	$h1 = $group['groupname'];
	$fa = 'share-alt';

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<dl class="dl-horizontal">';
	echo '<dt>Status</dt>';

	if ($group_schema = $app['groups']->get_schema($group['host']))
	{
		echo '<dd><span class="btn btn-info btn-xs">eLAND server</span>';

		if (!$app['config']->get('template_lets', $group_schema))
		{
			echo ' <span class="btn btn-danger btn-xs"><i class="fa fa-exclamation-triangle"></i> niet geconfigureerd als LETS-groep</span>';
		}

		if (!$app['config']->get('interlets_en', $group_schema))
		{
			echo ' <span class="btn btn-danger btn-xs"><i class="fa fa-exclamation-triangle"></i> interLETS niet ingeschakeld in configuratie</span>';
		}

		echo '</dd>';
	}
	else
	{
		echo '<dd><i><span data-elas-soap-status="' . $group['id']. '">';
		echo 'bezig met eLAS soap status te bekomen...</span></i>';
		echo '</dd>';

	}

	echo '<dt>Groepsnaam</dt>';
	echo '<dd>' . $group['groupname'] .'</dd>';

	echo '<dt>API methode</dt>';
	echo '<dd>' . $group['apimethod'] .'</dd>';

	echo '<dt>API key</dt>';
	echo '<dd>' . $group['remoteapikey'] .'</dd>';

	echo '<dt>Lokale LETS code</dt>';
	echo '<dd>';

	if ($user)
	{
		echo aphp('users', ['id' => $user['id']], $group['localletscode'], 'btn btn-default btn-xs', 'Ga naar het interSysteem account');

		if (!in_array($user['status'], [1, 2, 7]))
		{
			echo ' ' . aphp('users', ['edit' => $user['id']], 'Status!', 'btn btn-danger btn-xs',
				'Het interSysteem-account heeft een ongeldige status. De status moet van het type extern, actief of uitstapper zijn.',
				'exclamation-triangle');
		}
		if ($user['accountrole'] != 'interlets')
		{
			echo ' ' . aphp('users', ['edit' => $user['id']], 'Rol!', 'btn btn-danger btn-xs',
				'Het interSysteem-account heeft een ongeldige rol. De rol moet van het type interSysteem zijn.',
				'fa-exclamation-triangle');
		}
	}
	else
	{
		echo $group['localletscode'];

		if ($group['apimethod'] != 'internal' && !$user)
		{
			echo ' <span class="label label-danger" title="Er is geen account gevonden met deze code">';
			echo '<i class="fa fa-exclamation-triangle"></i> Account</span>';
		}
	}

	echo '</dd>';

	echo '<dt>Remote code</dt>';
	echo '<dd>';
	echo $group['myremoteletscode'];
	echo '</dd>';

	echo '<dt>URL</dt>';
	echo '<dd>';
	echo $group['url'];
	echo '</dd>';

	echo '<dt>Preshared Key</dt>';
	echo '<dd>';
	echo $group['presharedkey'];
	echo '</dd>';
	echo '</dl>';

	echo '</div></div>';

	echo get_schemas_groups();

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * list
 */

$groups = $app['db']->fetchAll('select * from letsgroups');

$letscodes = [];

foreach ($groups as $key => $g)
{
	$h = strtolower(parse_url($g['url'], PHP_URL_HOST));

	$letscodes[] = $g['localletscode'];

	if ($app['groups']->get_schema($h))
	{
		$s = $app['groups']->get_schema($h);

		$groups[$key]['eland'] = true;
		$groups[$key]['schema'] = $s;

		$groups[$key]['user_count'] = $app['db']->fetchColumn('select count(*)
			from ' . $s . '.users
			where status in (1, 2)');
	}
	else if ($g['apimethod'] == 'internal')
	{
		$groups[$key]['user_count'] = $app['db']->fetchColumn('select count(*)
			from users
			where status in (1, 2)');
	}
	else
	{
		$groups[$key]['user_count'] = $app['predis']->get($h . '_active_user_count');
	}
}

$users_letscode = [];

$interlets_users = $app['db']->executeQuery('select id, status, letscode, accountrole
	from users
	where letscode in (?)',
	[$letscodes],
	[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

foreach ($interlets_users as $u)
{
	$users_letscode[$u['letscode']] = [
		'id'			=> $u['id'],
		'status'		=> $u['status'],
		'accountrole'	=> $u['accountrole'],
	];
}

$top_buttons .= aphp('intersystem', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Groep toevoegen', 'plus', true);

$h1 = 'eLAS/eLAND InterSysteem';
$fa = 'share-alt';

include __DIR__ . '/include/header.php';

if (count($groups))
{
	echo '<div class="panel panel-primary printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-bordered table-hover table-striped footable">';
	echo '<thead>';
	echo '<tr>';
	echo '<th data-sort-initial="true">Account</th>';
	echo '<th>Groep</th>';
	echo '<th data-hide="phone">leden</th>';
	echo '<th data-hide="phone, tablet" data-sort-ignore="true">api</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($groups as $g)
	{
		echo '<tr>';
		echo '<td>';

		if ($g['apimethod'] == 'elassoap')
		{
			$user = $users_letscode[$g['localletscode']];

			if ($user)
			{
				echo aphp('users', ['id' => $user['id']], $g['localletscode'], 'btn btn-default btn-xs', 'Ga naar het interSysteem account');
				if (!in_array($user['status'], [1, 2, 7]))
				{
					echo ' ' . aphp('users', ['edit' => $user['id']], 'Status!', 'btn btn-danger btn-xs',
						'Het interSysteem-account heeft een ongeldige status. De status moet van het type extern, actief of uitstapper zijn.',
						'exclamation-triangle');
				}
				if ($user['accountrole'] != 'interlets')
				{
					echo ' ' . aphp('users', ['edit' => $user['id']], 'Rol!', 'btn btn-danger btn-xs',
						'Het interSysteem-account heeft een ongeldige rol. De rol moet van het type interSysteem zijn.',
						'fa-exclamation-triangle');
				}
			}
			else
			{
				echo $g['localletscode'];

				if ($g['apimethod'] != 'internal' && !$user)
				{
					echo ' <span class="label label-danger" title="Er is geen account gevonden met deze code">';
					echo '<i class="fa fa-exclamation-triangle"></i> Account</span>';
				}
			}
		}
		echo '</td>';

		echo '<td>';

		echo aphp('intersystem', ['id' => $g['id']], $g['groupname']);

		if (isset($g['eland']))
		{
			echo ' <span class="label label-info" title="Deze letsgroep bevindt zich op dezelfde eland-server">';
			echo 'eLAND</span>';

			if (!$app['config']->get('template_lets', $g['schema']))
			{
				echo ' <span class="label label-danger" title="Deze groep is niet geconfigureerd als Tijdsbank.">';
				echo '<i class="fa fa-exclamation-triangle"></i> geen Tijdsbank</span>';
			}

			if (!$app['config']->get('interlets_en', $g['schema']))
			{
				echo ' <span class="label label-danger" title="interSysteem is niet ingeschakeld in de configuratie van deze groep.">';
				echo '<i class="fa fa-exclamation-triangle"></i> geen interSysteem</span>';
			}
		}

		echo '</td>';

		echo '<td>' . $g['user_count'] . '</td>';

		echo '<td>' . $g['apimethod'] . '</td>';
		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div></div>';
}
else
{
	echo '<div class="panel panel-primary">';
	echo '<div class="panel-heading">';
	echo '<p>Er zijn nog geen interSysteem groepen.</p>';
	echo '</div></div>';
}

echo get_schemas_groups();

include __DIR__ . '/include/footer.php';
exit;

/**
 *
 */

function get_schemas_groups():string
{
	global $app;

	$out = '<p><ul>';
	$out .= '<li>Een groep van het type internal aanmaken is niet nodig in eLAND (in tegenstelling tot eLAS). Interne groepen worden genegeerd!</li>';
	$out .= '</ul></p>';

	$out .= '<div class="panel panel-default"><div class="panel-heading">';
	$out .= '<h3>Verbindingen met eLAS. Zie <a href="https://eland.letsa.net/admin-elas-interlets-koppeling-maken.html">hier</a> voor de procedure.</h3>';
	$out .= '<p><small>Voor verbindingen met eLAND zie onder!</small></p>';
	$out .= '</div>';
	$out .= '<ul>';
	$out .= '<li> Kies \'elassoap\' als API methode.</li>';
	$out .= '<li> De API key moet je aanvragen bij de beheerder van de andere installatie, het is een sleutel die je eigen eLAS toelaat om met de andere eLAS te praten. </li>';
	$out .= '<li> Lokale LETS Code is de letscode waarmee de andere groep op deze installatie bekend is, deze gebruiker moet al bestaan</li>';
	$out .= '<li> Remote LETS code is de letscode waarmee deze installatie bij de andere groep bekend is, deze moet aan de andere kant aangemaakt zijn.</li>';
	$out .= '<li> URL is de weblocatie van de andere installatie';
	$out .= '<li> Preshared Key is een gedeelde sleutel waarmee interSysteem transacties ondertekend worden.  Deze moet identiek zijn aan de preshared key voor de lets-rekening van deze installatie aan de andere kant</li>';
	$out .= '</ul>';
	$out .= '</div>';

	$out .= '<div class="panel panel-default">';
	$out .= '<div class="panel-heading">';
	$out .= '<h3>Verbindingen leggen met andere eLAND installaties.</h3>';
	$out .= '</div>';
	$out .= '<ul>';
	$out .= '<li>Met letsgroepen die eLAND gebruiken kan op een vereenvoudigde manier verbinding gelegd worden zonder ';
	$out .= 'het uitwisselen van apikeys, preshared keys en remote letscodes. Dit is mogelijk omdat alle eLAND installaties zich op ';
	$out .= 'dezelfde server bevinden.</li>';
	$out .= '<li>Contacteer altijd eerst vooraf de andere groep waarmee je een interSysteem verbinding wil opzetten. Vraag of zij ook geïnteresseerd zijn.</li>';
	$out .= '<li>Voor het leggen van een verbinding, kijk in de tabel hieronder. ';
	$out .= 'Maak de referentie naar de Tijdsbank aan door op \'Creëer\' in kolom \'lok.groep\' te klikken en vervolgens toevoegen. Dan, weer in de tabel onder, ';
	$out .= 'klik je op knop \'Creëer\' in de kolom \'lok.account\'. ';
	$out .= 'Vul een postcode in en klik op \'toevoegen\'. Nu de Tijdsbank en het interSysteem account aangemaakt zijn wil dat zeggen dat jouw groep toestemming geeft aan de andere groep voor de interSysteem verbinding. Wanneer ';
	$out .= 'de andere groep op dezelfde wijze een groep en interSysteem account aanmaakt is de verbinding compleet. ';
	$out .= 'In alle vier kolommen (lok.groep, lok.account, rem.groep, rem.account) zie je dan <span class="btn btn-success btn-xs">OK</span>.</li>';
	$out .= '</ul>';

	$url_ary = [];

	foreach ($app['groups']->get_hosts() as $h)
	{
		$url_ary[] = $app['protocol'] . $h;
	}

	$loc_url_ary = $loc_group_ary = $loc_account_ary = [];
	$rem_group_ary =  $rem_account_ary = $group_user_count_ary = [];
	$loc_letscode_ary = [];

	$groups = $app['db']->executeQuery('select localletscode, url, id
		from letsgroups
		where url in (?)',
		[$url_ary],
		[\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

	foreach ($groups as $group)
	{
		$loc_letscode_ary[] = $group['localletscode'];
		$h = strtolower(parse_url($group['url'], PHP_URL_HOST));
		$loc_group_ary[$h] = $group;
	}

	$interlets_accounts = $app['db']->executeQuery('select id, letscode, status, accountrole
		from users
		where letscode in (?)',
		[$loc_letscode_ary],
		[\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

	foreach ($interlets_accounts as $u)
	{
		$loc_account_ary[$u['letscode']] = $u;
	}

	foreach ($app['groups']->get_schemas() as $h => $s)
	{
		$rem_group = $app['db']->fetchAssoc('select localletscode, url, id
			from ' . $s . '.letsgroups
			where url = ?', [$app['base_url']]);

		$group_user_count_ary[$s] = $app['db']->fetchColumn('select count(*)
			from ' . $s . '.users
			where status in (1, 2)');

		if ($rem_group)
		{
			$rem_group_ary[$h] = $rem_group;

			if ($rem_group['localletscode'])
			{
				$rem_account = $app['db']->fetchAssoc('select id, letscode, status, accountrole
					from ' . $s . '.users where letscode = ?', [$rem_group['localletscode']]);

				if ($rem_account)
				{
					$rem_account_ary[$h] = $rem_account;
				}
			}
		}
	}

	$out .= '<div class="panel-heading">';
	$out .= '<h3>Groepen die eLAND gebruiken</h3>';
	$out .= '</div>';

	$out .= '<table class="table table-bordered table-hover table-striped footable">';
	$out .= '<thead>';
	$out .= '<tr>';
	$out .= '<th data-sort-initial="true">groepsnaam</th>';
	$out .= '<th data-hide="phone, tablet">domein</th>';
	$out .= '<th data-hide="phone, tablet">leden</th>';
	$out .= '<th>lok.groep</th>';
	$out .= '<th>lok.account</th>';
	$out .= '<th>rem.groep</th>';
	$out .= '<th>rem.account</th>';
	$out .= '</tr>';
	$out .= '</thead>';

	$out .= '<tbody>';

	$unavailable_explain = false;

	foreach($app['groups']->get_schemas() as $h => $s)
	{
		$out .= '<tr';

		if (!$app['config']->get('template_lets', $s) || !$app['config']->get('interlets_en', $s))
		{
			$out .= ' class="danger"';

			$unavailable_explain = true;
		}

		$out .= '>';

		$out .= '<td>';
		$out .= $app['config']->get('systemname', $s);

		if (!$app['config']->get('template_lets', $s))
		{
			$out .= ' <span class="label label-danger" title="Deze groep is niet geconfigureerd als LETS groep">';
			$out .= '<i class="fa fa-exclamation-triangle">';
			$out .= '</i></span>';
		}

		if (!$app['config']->get('interlets_en', $s))
		{
			$out .= ' <span class="label label-danger" title="interSysteem is niet ingeschakeld in de configuratie">';
			$out .= '<i class="fa fa-exclamation-triangle">';
			$out .= '</i></span>';
		}

		$out .= '</td>';

		$out .= '<td>';
		$out .= $h;
		$out .= '</td>';

		$out .= '<td>';
		$out .= $group_user_count_ary[$s];
		$out .= '</td>';

		if ($app['this_group']->get_schema() == $s)
		{
			$out .= '<td colspan="4">';
			$out .= 'eigen groep';
			$out .= '</td>';
		}
		else
		{
			$out .= '<td>';

			if (isset($loc_group_ary[$h]) && is_array($loc_group_ary[$h]))
			{
				$loc_group = $loc_group_ary[$h];

				$out .= aphp('intersystem', ['id' => $loc_group['id']], 'OK', 'btn btn-success btn-xs');
			}
			else
			{
				if ($app['config']->get('template_lets', $s) && $app['config']->get('interlets_en', $s))
				{
					$out .= aphp('intersystem', ['add' => 1, 'add_schema' => $s], 'Creëer', 'btn btn-default btn-xs');
				}
				else
				{
					$out .= '<i class="fa fa-times text-danger"></i>';
				}
			}

			$out .= '</td>';
			$out .= '<td>';

			if (isset($loc_group_ary[$h]))
			{
				$loc_group = $loc_group_ary[$h];

				if (is_array($loc_acc = $loc_account_ary[$loc_group['localletscode']]))
				{
					if ($loc_acc['accountrole'] != 'interlets')
					{
						$out .= aphp('users', ['edit' => $loc_acc['id']], 'rol', 'btn btn-warning btn-xs',
							'De rol van het account moet van het type interSysteem zijn.');
					}
					else if (!in_array($loc_acc['status'], [1, 2, 7]))
					{
						$out .= aphp('users', ['edit' => $loc_acc['id']], 'status', 'btn btn-warning btn-xs',
							'De status van het account moet actief, uitstapper of extern zijn.');
					}
					else
					{
						$out .= aphp('users', ['id' => $loc_acc['id']], 'OK', 'btn btn-success btn-xs');
					}
				}
				else
				{
					$out .= aphp('users', ['add' => 1, 'intersystem_code' => $loc_group['localletscode']], 'Creëer', 'btn btn-default btn-xs text-danger',
						'Creëer een interSysteem-account met gelijke code en status extern.');
				}
			}
			else
			{
				$out .= '<i class="fa fa-times text-danger"></i>';
			}
			$out .= '</td>';
			$out .= '<td>';
			if (isset($rem_group_ary[$h]))
			{
				$out .= '<span class="btn btn-success btn-xs">OK</span>';
			}
			else
			{
				$out .= '<i class="fa fa-times text-danger"></i>';
			}
			$out .= '</td>';
			$out .= '<td>';

			if (isset($rem_account_ary[$h]))
			{
				$rem_acc = $rem_account_ary[$h];

				if ($rem_acc['accountrole'] != 'interlets')
				{
					$out .= '<span class="btn btn-warning btn-xs" title="De rol van het account ';
					$out .= 'moet van het type interSysteem zijn.">rol</span>';
				}
				else if (!in_array($rem_acc['status'], [1, 2, 7]))
				{
					$out .= '<span class="btn btn-warning btn-xs" title="De status van het account ';
					$out .= 'moet actief, uitstapper of extern zijn.">rol</span>';
				}
				else
				{
					$out .= '<span class="btn btn-success btn-xs">OK</span>';
				}
			}
			else
			{
				$out .= '<i class="fa fa-times text-danger"></i>';
			}

			$out .= '</td>';

			$out .= '</tr>';
		}
	}
	$out .= '</tbody>';
	$out .= '</table>';

	if ($unavailable_explain)
	{
		$out .= '<ul class="list-group">';
		$out .= '<li class="list-group-item danger"><span class="bg-danger">Groepen gemarkeerd in Rood ';
		$out .= 'zijn niet beschikbaar voor interSysteem verbindingen.</span></li>';
		$out .= '</ul>';
	}

	$out .= '</div></div>';

	return $out;
}

function cancel($id = null)
{
	$params = [];

	if ($id)
	{
		$params['id'] = $id;
	}

	header('Location: ' . generate_url('interlets', $params));
	exit;
}
