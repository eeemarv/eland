<?php
$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$id = (isset($_GET['id'])) ? $_GET['id'] : false;
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$add = (isset($_GET['add'])) ? true : false;
$add_schema = (isset($_GET['add_schema'])) ? $_GET['add_schema'] : false;

$submit = (isset($_POST['zend'])) ? true : false;

if ($id || $edit || $del)
{
	$id = ($id) ?: (($edit) ?: $del);

	$group = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($id));

	if (!$group)
	{
		$alert->error('Groep niet gevonden.');
		cancel();
	}
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

		$errors = array();

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
			$errors[] = 'De lokale letscode mag maximaal 20 tekens lang zijn.';
		}

		if (strlen($group['myremoteletscode']) > 20)
		{
			$errors[] = 'De remote letscode mag maximaal 20 tekens lang zijn.';
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

		if ($error_token = get_error_form_token())
		{
			$errors[] = $error_token;
		}

		$shortname = str_replace(' ', '', $group['groupname']);
		$shortname = substr($shortname, 0, 50);
		$group['shortname'] = strtolower($shortname);

		if ($edit)
		{
			if ($db->fetchColumn('select id
				from letsgroups
				where url = ?
					and id <> ?', array($group['url'], $edit)))
			{
				$errors[] = 'Er bestaat al een letsgroep met deze url.';
			}

			if ($db->fetchColumn('select id
				from letsgroups
				where localletscode = ?
					and id <> ?', array($group['localletscode'], $edit)))
			{
				$errors[] = 'Er bestaat al een letsgroep met deze lokale letscode.';
			}

			if (!count($errors))
			{
				if ($db->update('letsgroups', $group, array('id' => $id)))
				{
					$alert->success('Letsgroep aangepast.');

					clear_interlets_groups_cache();
					
					cancel($edit);
				}

				$alert->error('Letsgroep niet aangepast.');
			}
		}
		else
		{
			if ($db->fetchColumn('select id from letsgroups where url = ?', array($group['url'])))
			{
				$errors[] = 'Er bestaat al een letsgroep met deze url.';
			}

			if ($db->fetchColumn('select id from letsgroups where localletscode = ?', array($group['localletscode'])))
			{
				$errors[] = 'Er bestaat al een letsgroep met deze lokale letscode.';
			}

			if (!count($errors))
			{
				if ($db->insert('letsgroups', $group))
				{
					$alert->success('Letsgroep opgeslagen.');

					$id = $db->lastInsertId('letsgroups_id_seq');

					clear_interlets_groups_cache();
					
					cancel($id);
				}

				$alert->error('Letsgroep niet opgeslagen.');
			}
		}

		if (count($errors))
		{
			$alert->error($errors);
		}
	}

	if ($add)
	{
		$group = array();
	}

	if ($add_schema && $add)
	{
		if (isset($hosts[$add_schema]))
		{
			$group['url'] = $app_protocol . $hosts[$add_schema];
			$group['groupname'] = readconfigfromdb('systemname', $add_schema);
			$group['localletscode'] = readconfigfromdb('systemtag', $add_schema);
		}
	}

	$h1 = 'LETS groep ';
	$h1 .= ($edit) ? 'aanpassen' : 'toevoegen';
	$fa = 'share-alt';

	include $rootpath . 'includes/inc_header.php';

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
	render_select_options(array(
		'elassoap'	=> 'eLAND naar eLAND of eLAS (elassoap)',
		'internal'	=> 'Intern (eigen installatie - niet gebruiken)',
		'mail'		=> 'E-mail',
	), $group['apimethod']);
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
	$canc = ($edit) ? 'id=' . $edit : '';
	echo aphp('interlets', $canc, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	render_schemas_groups();

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * delete
 */
if ($del)
{
	if ($submit)
	{

		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);
			cancel();
		}

		if($db->delete('letsgroups', array('id' => $del)))
		{
			$alert->success('Letsgroep verwijderd.');

			clear_interlets_groups_cache();
			
			cancel();
		}

		$alert->error('Letsgroep niet verwijderd.');
	}

	$h1 = 'Letsgroep verwijderen: ' . $group['groupname'];
	$fa = 'share-alt';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<p class="text-danger">Ben je zeker dat deze groep';
	echo ' moet verwijderd worden?</p>';
	echo '<div><p>';
	echo '<form method="post">';

	echo aphp('interlets', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	generate_form_token();

	echo "</form></p>";
	echo "</div>";

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * See settings of a group
 */
if ($id)
{
	if (isset($group['url']))
	{
		$group['host'] = get_host($group);
	}

	$top_buttons .= aphp('interlets', 'add=1', 'Toevoegen', 'btn btn-success', 'Letsgroep toevoegen', 'plus', true);
	$top_buttons .= aphp('interlets', 'edit=' . $id, 'Aanpassen', 'btn btn-primary', 'Letsgroep aanpassen', 'pencil', true);
	$top_buttons .= aphp('interlets', 'del=' . $id, 'Verwijderen', 'btn btn-danger', 'Letsgroep verwijderen', 'times', true);
	$top_buttons .= aphp('interlets', '', 'Lijst', 'btn btn-default', 'Lijst letsgroepen', 'share-alt', true);

	$includejs .= '<script src="' . $rootpath . 'js/elas_soap_status.js"></script>';

	$h1 = $group['groupname'];
	$fa = 'share-alt';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<dl class="dl-horizontal">';
	echo '<dt>Status</dt>';

	if ($schemas[$group['host']])
	{
		echo '<dd><span class="btn btn-info btn-xs">eLAND server</span></dd>';
	}
	else
	{
		echo '<dd><i><span data-elas-soap-status="' . generate_url('ajax/elas_soap_status', 'group_id=' . $group['id']) . '">';
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
	echo '<dd>' . $group['localletscode'] .'</dd>';

	echo '<dt>Remote LETS code</dt>';
	echo '<dd>' . $group['myremoteletscode'] .'</dd>';

	echo '<dt>URL</dt>';
	echo '<dd>' . $group['url'] .'</dd>';

	echo '<dt>Preshared Key</dt>';
	echo '<dd>' . $group['presharedkey'].'</dd>';
	echo '</dl>';

	echo '</div></div>';

	render_schemas_groups();

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * list
 */

$groups = $db->fetchAll('SELECT * FROM letsgroups');

$letscodes = array();

foreach ($groups as $key => $g)
{
	$h = get_host($g);

	$letscodes[] = $g['localletscode'];

	if ($s = $schemas[$h])
	{
		$groups[$key]['eland'] = true;
		$groups[$key]['schema'] = $s;
		$groups[$key]['user_count'] = $db->fetchColumn('select count(*)
			from ' . $s . '.users
			where status in (1, 2)');
	}
	else if ($g['apimethod'] == 'internal')
	{
		$groups[$key]['user_count'] = $db->fetchColumn('select count(*)
			from users
			where status in (1, 2)');
	}
	else
	{
		$groups[$key]['user_count'] = $redis->get($g['url'] . '_active_user_count');
	}
}

$users_letscode = array();

$interlets_users = $db->executeQuery('select id, status, letscode, accountrole
	from users
	where letscode in (?)',
	array($letscodes),
	array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));

foreach ($interlets_users as $u)
{
	$users_letscode[$u['letscode']] = array(
		'id'			=> $u['id'],
		'status'		=> $u['status'],
		'accountrole'	=> $u['accountrole'],
	);
}

$top_buttons .= aphp('interlets', 'add=1', 'Toevoegen', 'btn btn-success', 'Groep toevoegen', 'plus', true);

$h1 = 'InterLETS groepen';
$fa = 'share-alt';

include $rootpath . 'includes/inc_header.php';

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
				echo aphp('users', 'id=' . $user['id'], $g['localletscode'], 'btn btn-default btn-xs', 'Ga naar het interlets account');
				if (!in_array($user['status'], array(1, 2, 7)))
				{
					echo aphp('users', 'edit=' . $user['id'], 'Status!', 'btn btn-default btn-xs text-danger',
						'Het interlets-account heeft een ongeldige status. De status moet van het type extern, actief of uitstapper zijn.',
						'exclamation-triangle');
				}
				if ($user['accountrole'] != 'interlets')
				{
					echo aphp('users', 'edit=' . $user['id'], 'Rol!', 'btn btn-default btn-xs text-danger',
						'Het interlets-account heeft een ongeldige rol. De rol moet van het type interlets zijn.',
						'fa-exclamation-triangle');
				}
			}
			else
			{
				echo $g['localletscode'];

				if ($g['apimethod'] != 'internal' && !$user)
				{
					echo aphp('users', 'add=1&interlets=' . $g['localletscode'], 'Account!', 'btn btn-default btn-xs text-danger',
						'Creëer een interlets-account met gelijke letscode en status extern.',
						'exclamation-triangle');
				}
			}
		}
		echo '</td>';

		echo '<td>';

		echo aphp('interlets', 'id=' . $g['id'], $g['groupname']);

		if ($g['eland'])
		{
			echo ' <span class="label label-info" title="Deze letsgroep bevindt zich op dezelfde eland-server">';
			echo 'eLAND</span>';
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
	echo '<p>Er zijn nog geen interletsgroepen.</p>';
	echo '</div></div>';
}

render_schemas_groups();

include $rootpath . 'includes/inc_footer.php';
exit;

/**
 *
 */

function render_schemas_groups()
{
	global $schema, $db, $base_url, $schemas, $hosts, $app_protocol;

	echo '<p><ul>';
	echo '<li>Een groep van het type internal aanmaken is niet nodig in eLAND (in tegenstelling tot eLAS). Interne groepen worden genegeerd!</li>';
	echo '</ul></p>';

	echo '<div class="panel panel-default"><div class="panel-heading">';
	echo '<h3>Verbindingen met eLAS. Zie <a href="http://www.elasproject.org/content/hoe-maak-ik-een-interlets-koppeling">hier</a> voor de procedure.</h3>';
	echo '<p><small>Voor verbindingen met eLAND zie onder!</small></p>';
	echo '</div>';
	echo '<ul>';
	echo '<li> API methode bepaalt de connectie naar de andere groep, geldige waarden zijn internal, elassoap en mail. Internal wordt genegeerd in eLAND.</li>';
	echo '<li> De API key moet je aanvragen bij de beheerder van de andere installatie, het is een sleutel die je eigen eLAS toelaat om met de andere eLAS te praten. </li>';
	echo '<li> Lokale LETS Code is de letscode waarmee de andere groep op deze installatie bekend is, deze gebruiker moet al bestaan</li>';
	echo '<li> Remote LETS code is de letscode waarmee deze installatie bij de andere groep bekend is, deze moet aan de andere kant aangemaakt zijn.</li>';
	echo '<li> URL is de weblocatie van de andere installatie';
	echo '<li> Preshared Key is een gedeelde sleutel waarmee interlets transacties ondertekend worden.  Deze moet identiek zijn aan de preshared key voor de lets-rekening van deze installatie aan de andere kant</li>';
	echo '</ul>';
	echo '</div>';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';
	echo '<h3>Verbindingen leggen met andere eLAND installaties.</h3>';
	echo '</div>';
	echo '<ul>';
	echo '<li>Met letsgroepen die eLAND gebruiken kan op een vereenvoudigde manier verbinding gelegd worden zonder ';
	echo 'het uitwisselen van apikeys, preshared keys en remote letscodes. Dit is mogelijk omdat alle eLAND installaties zich op ';
	echo 'dezelfde server bevinden.</li>';
	echo '<li>Contacteer altijd eerst vooraf de andere groep waarmee je wil interletsen. Vraag of zij ook geïnteresseerd zijn in een verbinding.</li>';
	echo '<li>Voor het leggen van een verbinding, kijk in de tabel hieronder. ';
	echo 'Maak de referentie naar de letsgroep aan door op \'Creëer\' in kolom \'lok.groep\' te klikken en vervolgens toevoegen. Dan, weer in de tabel onder, ';
	echo 'klik je op knop \'Creëer\' in de kolom \'lok.account\'. ';
	echo 'Vul een postcode in en klik op \'toevoegen\'. Nu de letsgroep en het interlets account aangemaakt zijn wil dat zeggen dat jouw groep toestemming geeft aan de andere groep om te interletsen. Wanneer ';
	echo 'de andere groep op dezelfde wijze een letsgroep en interlets account aanmaakt is de verbinding compleet. ';
	echo 'In alle vier kolommen (lok.groep, lok.account, rem.groep, rem.account) zie je dan <span class="btn btn-success btn-xs">OK</span>.</li>';
	echo '</ul>';

	$url_ary = array();

	foreach ($hosts as $h)
	{
		$url_ary[] = $app_protocol . $h;
	}

	$loc_url_ary = $loc_group_ary = $loc_account_ary = array();
	$rem_group_ary =  $rem_account_ary = $group_user_count_ary = array();

	$groups = $db->executeQuery('select localletscode, url, id
		from letsgroups
		where url in (?)',
		array($url_ary),
		array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

	foreach ($groups as $group)
	{
		$loc_letscode_ary[] = $group['localletscode'];
		$h = get_host($group);
		$loc_group_ary[$h] = $group;
	}

	$interlets_accounts = $db->executeQuery('select id, letscode, status, accountrole
		from users
		where letscode in (?)',
		array($loc_letscode_ary),
		array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

	foreach ($interlets_accounts as $u)
	{
		$loc_account_ary[$u['letscode']] = $u;
	}

	foreach ($schemas as $h => $s)
	{
		$rem_group = $db->fetchAssoc('select localletscode, url, id
			from ' . $s . '.letsgroups
			where url = ?', array($base_url));

		$group_user_count_ary[$s] = $db->fetchColumn('select count(*)
			from ' . $s . '.users
			where status in (1, 2)');

		if ($rem_group)
		{
			$rem_group_ary[$h] = $rem_group;

			if ($rem_group['localletscode'])
			{
				$rem_account = $db->fetchAssoc('select id, letscode, status, accountrole
					from ' . $s . '.users where letscode = ?', array($rem_group['localletscode']));

				if ($rem_account)
				{
					$rem_account_ary[$h] = $rem_account;
				}
			}
		}
	}

	echo '<div class="panel-heading">';
	echo '<h3>eLAND interlets groepen</h3>';
	echo '</div>';

	echo '<table class="table table-bordered table-hover table-striped footable">';
	echo '<thead>';
	echo '<tr>';
	echo '<th data-sort-initial="true" data-hide="phone, tablet">tag</th>';
	echo '<th>groepsnaam</th>';
	echo '<th data-hide="phone, tablet">url</th>';
	echo '<th data-hide="phone, tablet">leden</th>';
	echo '<th>lok.groep</th>';
	echo '<th>lok.account</th>';
	echo '<th>rem.groep</th>';
	echo '<th>rem.account</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($schemas as $h => $s)
	{
		echo '<tr>';

		echo '<td>';
		echo readconfigfromdb('systemtag', $s);
		echo '</td>';

		echo '<td>';
		echo readconfigfromdb('systemname', $s);
		echo '</td>';

		echo '<td>';
		echo $h;
		echo '</td>';

		echo '<td>';
		echo $group_user_count_ary[$s];
		echo '</td>';

		if ($schema == $s)
		{
			echo '<td colspan="4">';
			echo 'eigen groep';
			echo '</td>';
		}
		else
		{
			echo '<td>';
			if (is_array($loc_group =  $loc_group_ary[$h]))
			{
				echo aphp('interlets', 'id=' . $loc_group['id'], 'OK', 'btn btn-success btn-xs');
			}
			else
			{
				echo aphp('interlets', 'add=1&add_schema=' . $s, 'Creëer', 'btn btn-default btn-xs');
			}
			echo '</td>';
			echo '<td>';
			if ($loc_group)
			{
				if (is_array($loc_acc = $loc_account_ary[$loc_group['localletscode']]))
				{
					if ($loc_acc['accountrole'] != 'interlets')
					{
						echo aphp('users', 'edit=' . $loc_acc['id'], 'rol', 'btn btn-warning btn-xs',
							'De rol van het account moet van het type interlets zijn.');
					}
					else if (!in_array($loc_acc['status'], array(1, 2, 7)))
					{
						echo aphp('users', 'edit=' . $loc_acc['id'], 'status', 'btn btn-warning btn-xs',
							'De status van het account moet actief, uitstapper of extern zijn.');
					}
					else
					{
						echo aphp('users', 'id=' . $loc_acc['id'], 'OK', 'btn btn-success btn-xs');
					}
				}
				else
				{
					echo aphp('users', 'add=1&interlets=' . $loc_group['localletscode'], 'Creëer', 'btn btn-default btn-xs text-danger',
						'Creëer een interlets-account met gelijke letscode en status extern.');
				}
			}
			else
			{
				echo '<i class="fa fa-times text-danger"></i>';
			}
			echo '</td>';
			echo '<td>';
			if ($rem_group_ary[$h])
			{
				echo '<span class="btn btn-success btn-xs">OK</span>';
			}
			else
			{
				echo '<i class="fa fa-times text-danger"></i>';
			}
			echo '</td>';
			echo '<td>';
			if ($rem_acc = $rem_account_ary[$h])
			{
				if ($rem_acc['accountrole'] != 'interlets')
				{
					echo '<span class="btn btn-warning btn-xs" title="De rol van het account ';
					echo 'moet van het type interlets zijn.">rol</span>';
				}
				else if (!in_array($rem_acc['status'], array(1, 2, 7)))
				{
					echo '<span class="btn btn-warning btn-xs" title="De status van het account ';
					echo 'moet actief, uitstapper of extern zijn.">rol</span>';
				}
				else
				{
					echo '<span class="btn btn-success btn-xs">OK</span>';
				}
			}
			else
			{
				echo '<i class="fa fa-times text-danger"></i>';
			}
			echo '</td>';

			echo '</tr>';
		}
	}
	echo '</tbody>';
	echo '</table>';
	echo '</div></div>';
}

function cancel($id = null)
{
	$id = ($id) ? 'id=' . $id : '';

	header('Location: ' . generate_url('interlets', $id));
	exit;
}
