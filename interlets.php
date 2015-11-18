<?php
$rootpath = './';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$login = (isset($_GET['login'])) ? $_GET['login'] : false;
$location = (isset($_GET['location'])) ? $_GET['location'] : '';
$id = (isset($_GET['id'])) ? $_GET['id'] : false;
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$add = (isset($_GET['add'])) ? true : false;
$add_schema = (isset($_GET['add_schema'])) ? $_GET['add_schema'] : false;

$submit = (isset($_POST['zend'])) ? true : false;

if (($id || $edit || $del || $add) && !$s_admin)
{
	$alert->error('Je hebt onvoldoende rechten voor deze pagina.');
	cancel();
}

if ($id || $edit || $del || $login)
{
	$id = ($id) ?: (($edit) ?: (($del) ?: $login));

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

		unset($group['zend']);

		$errors = array();

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
					cancel($id);
				}

				$alert->error('Letsgroep niet opgeslagen.');
			}
		}

		if (count($errors))
		{
			$alert->error(implode('<br>', $errors));
		}
	}

	if ($add)
	{
		$group = array();
	}

	if ($add_schema && $add)
	{
		list($schemas, $domains) = get_schemas_domains(true);

		if ($url = $domains[$add_schema])
		{
			$group['url'] = $url;
			$group['groupname'] = $group['shortname'] = readconfigfromschema('systemname', $add_schema);
			$group['localletscode'] = readconfigfromschema('systemtag', $add_schema);
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
	echo 'value="' . $group['groupname'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="shortname" class="col-sm-2 control-label">Korte naam / groepscode ';
	echo '<small><i>(kleine letters zonder spaties)</i></small></label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="shortname" name="shortname" ';
	echo 'value="' . $group['shortname'] . '">';
	echo '</div>';
	echo '</div>';

	/*
	echo '<div class="form-group">';
	echo '<label for="prefix" class="col-sm-2 control-label">Prefix ';
	echo '<small><i>(kleine letters zonder spaties)</i></small></label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="prefix" name="prefix" ';
	echo 'value="' . $group['prefix'] . '">';
	echo '</div>';
	echo '</div>';
	*/

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
	echo 'URL (incluis http://)';
	echo '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="url" class="form-control" id="url" name="url" ';
	echo 'value="' . $group['url'] . '">';
	echo '</div>';
	echo '</div>';

	/*
	echo '<div class="form-group">';
	echo '<label for="elassoapurl" class="col-sm-2 control-label">';
	echo 'SOAP URL <small><i>(voor eLAS, de URL met /soap erachter)</i></small>';
	echo '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="url" class="form-control" id="elassoapurl" name="elassoapurl" ';
	echo 'value="' . $group['elassoapurl'] . '">';
	echo '</div>';
	echo '</div>';
	*/

	echo '<div class="form-group">';
	echo '<label for="presharedkey" class="col-sm-2 control-label">';
	echo 'Preshared key';
	echo '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="presharedkey" name="presharedkey" ';
	echo 'value="' . $group['presharedkey'] . '">';
	echo '</div>';
	echo '</div>';

	$btn = ($edit) ? 'primary' : 'success';
	$canc = ($edit) ? 'id=' . $edit : '';
	echo aphp('interlets', $canc, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';

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
		if($db->delete('letsgroups', array('id' => $del)))
		{
			$alert->success('Letsgroep verwijderd.');
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

	echo "</form></p>";
	echo "</div>";

	echo '</div>';
	echo '</div>';
		
	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * See settings of a letsgroup (admin)
 */
if ($id && !$login)
{
	list($schemas, $domains) = get_schemas_domains(true);

	$top_buttons .= aphp('interlets', 'add=1', 'Toevoegen', 'btn btn-success', 'Letsgroep toevoegen', 'plus', true);
	$top_buttons .= aphp('interlets', 'edit=' . $id, 'Aanpassen', 'btn btn-primary', 'Letsgroep aanpassen', 'pencil', true);
	$top_buttons .= aphp('interlets', 'del=' . $id, 'Verwijderen', 'btn btn-danger', 'Letsgroep verwijderen', 'times', true);
	$top_buttons .= aphp('interlets', '', 'Lijst', 'btn btn-default', 'Lijst letsgroepen', 'share-alt', true);

	$h1 = $group['groupname'];
	$fa = 'share-alt';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<dl class="dl-horizontal">';
	echo '<dt>eLAS Soap status</dt>';

	if ($schemas[$group['url']])
	{
		echo '<dd><span class="btn btn-success btn-xs">server</span></dd>';
	}
	else
	{
		echo '<dd><i><div id="statusdiv">';
		$soapurl = $group['elassoapurl'] .'/wsdlelas.php?wsdl';
		$apikey = $group['remoteapikey'];
		$client = new nusoap_client($soapurl, true);
		$err = $client->getError();
		if (!$err)
		{
			$result = $client->call('getstatus', array('apikey' => $apikey));
			$err = $client->getError();
			if (!$err)
			{
				echo $result;
			}
		}
		echo '</div></i>';
		echo '</dd>';
	}

	echo '<dt>Groepnaam</dt>';
	echo '<dd>' .$group['groupname'] .'</dd>';

	echo '<dt>Korte naam</dt>';
	echo '<dd>' .$group['shortname'] .'</dd>';

//	echo '<dt>Prefix</dt>';
//	echo '<dd>' .$group['prefix'] .'</dd>';

	echo '<dt>API methode</dt>';
	echo '<dd>' .$group['apimethod'] .'</dd>';

	echo '<dt>API key</dt>';
	echo '<dd>' .$group['remoteapikey'] .'</dd>';

	echo '<dt>Lokale LETS code</dt>';
	echo '<dd>' .$group['localletscode'] .'</dd>';

	echo '<dt>Remote LETS code</dt>';
	echo '<dd>' .$group['myremoteletscode'] .'</dd>';

	echo '<dt>URL</dt>';
	echo '<dd>' .$group['url'] .'</dd>';

//	echo '<dt>SOAP URL</dt>';
//	echo '<dd>' .$group['elassoapurl'] .'</dd>';

	echo '<dt>Preshared Key</dt>';
	echo '<dd>' .$group['presharedkey'].'</dd>';
	echo '</dl>';

	echo '</div></div>';

	render_schemas_groups();

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/*
 * login
 */
if ($login)
{
	if (!$group['url'])
	{
		$alert->error('De url van de interLETS groep is niet ingesteld.');
		cancel();
	}

	if ($group['apimethod'] != 'elassoap')
	{
		$alert->error($err_group . 'Deze groep draait geen eLAS-soap, kan geen connectie maken');
		cancel();
	}

	$err_group = $group['groupname'] . ': ';

	list($schemas, $domains) = get_schemas_domains(true);

	$remote_schema = (isset($schemas[$group['url']])) ? $schemas[$group['url']] : false;

	if ($remote_schema)
	{
		// the letsgroup is on the same server

		$remote_group = $db->fetchAssoc('select * from ' . $remote_schema . '.letsgroups where url = ?', array($base_url));

		if (!$remote_group)
		{
			$alert->error('Deze interLETS groep heeft geen verbinding geconfirmeerd met deze groep. ');
			cancel();
		}

		if (!$remote_group['localletscode'])
		{
			$alert->error('Er is geen letscode ingesteld bij de interLETS groep voor deze groep.');
			cancel();
		}

		$remote_user = $db->fetchAssoc('select * from ' . $remote_schema . '.users where letscode = ?', array($remote_group['localletscode']));

		if (!$remote_user)
		{
			$alert->error('Geen interlets account aanwezig bij deze interLETS groep voor deze groep.');
			cancel();
		}

		if (!in_array($remote_user['status'], array(1, 2, 7)))
		{
			$alert->error('Geen correcte status van het interlets account bij deze interlets groep.');
			cancel();
		}

		if ($remote_user['accountrole'] != 'interlets')
		{
			$alert->error('Geen correcte rol van het interlets account bij deze interlets groep.');
			cancel();
		}

		$user = readuser($s_id);

		$mail = $db->fetchColumn('select c.value
			from contact c, type_contact tc
			where c.id_user = ?
				and tc.id = c.id_type_contact
				and tc.abbrev = \'mail\'', array($s_id));

		$ary = array(
			'id'			=> $s_id,
			'name'			=> $user['name'],
			'letscode'		=> $user['letscode'],
			'mail'			=> $mail,
			'systemtag'		=> readconfigfromdb('systemtag'),
			'systemname'	=> readconfigfromdb('systemname'),
			'url'			=> $base_url,
			'schema'		=> $schema,
		);

		$token = substr(md5(microtime() . $remote_schema), 0, 12);
		$key = $remote_schema . '_token_' . $token;
		$redis->set($key, serialize($ary));
		$redis->expire($key, 600);

		log_event('' ,'Soap' ,'Token ' . $token . ' generated');

		echo '<script>window.open("' . $group['url'] . '/login.php?token=' . $token . '&location=' . $location . '");';
		echo 'window.focus();';
		echo '</script>';

	}
	else
	{
		$soapurl = ($group['elassoapurl']) ? $group['elassoapurl'] : $group['url'] . '/soap';
		$soapurl = $soapurl . '/wsdlelas.php?wsdl';
		$apikey = $group['remoteapikey'];
		$client = new nusoap_client($soapurl, true);
		$err = $client->getError();
		if ($err)
		{
			$alert->error($err_group . 'Kan geen verbinding maken.');
		}
		else
		{
			$token = $client->call('gettoken', array('apikey' => $apikey));
			$err = $client->getError();
			if ($err)
			{
				$alert->error($err_group . 'Kan geen token krijgen.');
			}
			else
			{
				echo '<script>window.open("' . $group['url'] . '/login.php?token=' . $token . '&location=' . $location . '");';
				echo 'window.focus();';
				echo '</script>';
			}
		}
	}
}

/**
 * list
 */
$where = ($s_admin) ? '' : ' where apimethod <> \'internal\'';
$groups = $db->fetchAll('SELECT * FROM letsgroups' . $where);

list($schemas, $domains) = get_schemas_domains(true);

$letscodes = $groups_domains = $group_schemas = array();

foreach ($groups as $key => $g)
{
	$letscodes[] = $g['localletscode'];

	if ($s = $schemas[$g['url']])
	{
		$groups[$key]['server'] = true;
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

if ($s_admin)
{
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
}

$h1 = 'InterLETS groepen';
$fa = 'share-alt';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-primary printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo ($s_admin) ? '<th data-sort-initial="true">Account</th>' : '';
echo '<th>groepsnaam</th>';
echo '<th data-hide="phone">leden</th>';

if ($s_admin)
{
	echo '<th data-hide="phone, tablet">Admin</th>';	
	echo '<th data-hide="phone, tablet">api</th>';
}

echo '</tr>';
echo '</thead>';

echo '<tbody>';

$param = ($s_admin) ? 'id' : 'login';

foreach($groups as $g)
{
	$error = false;
	echo '<tr>';
	if ($s_admin)
	{
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
	}

	if ($g['apimethod'] == 'elassoap')
	{
		echo '<td>';
		echo aphp('interlets', 'login=' . $g['id'], $g['groupname'], false, 'login als gast op deze letsgroep');
		echo '</td>';
	}
	else
	{
		echo '<td>' . $g['groupname'] . '</td>';
	}

	echo '<td>' . $g['user_count'] . '</td>';

	if ($s_admin)
	{
		echo '<td>';
		echo aphp('interlets', 'id=' . $g['id'], 'Instellingen', 'btn btn-default btn-xs');

		if ($error)
		{
			echo ' <span class="fa fa-exclamation-triangle text-danger"></span>';
		}
		if ($g['server'])
		{
			echo ' <span class="label label-success" title="Deze letsgroep bevindt zich op dezelfde server">';
			echo 'server</span>';
		}
		echo '</td>';
		echo '<td>' . $g['apimethod'] . '</td>';
	}
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

if ($s_admin)
{
	render_schemas_groups($schemas);
}

include $rootpath . 'includes/inc_footer.php';

function render_schemas_groups()
{
	global $schema, $db, $base_url;

	echo '<p><ul>';
	echo '<li>Een groep van het type internal aanmaken is niet nodig in eLAS-Heroku (in tegenstelling tot eLAS). Interne groepen worden genegeerd!</li>';
	echo '</ul></p>';

	echo '<div class="panel panel-default"><div class="panel-heading">';
	echo '<p>Verbindingen met eLAS. Zie <a href="http://www.elasproject.org/content/hoe-maak-ik-een-interlets-koppeling">hier</a> voor de procedure.</p>';
	echo '</div>';
	echo '<ul>';
	echo '<li> API methode bepaalt de connectie naar de andere groep, geldige waarden zijn internal, elassoap en mail. Internal wordt genegeerd in eLAS-Heroku.</li>';
	echo '<li> De API key moet je aanvragen bij de beheerder van de andere installatie, het is een sleutel die je eigen eLAS toelaat om met de andere eLAS te praten. </li>';
	echo '<li> Lokale LETS Code is de letscode waarmee de andere groep op deze installatie bekend is, deze gebruiker moet al bestaan</li>';
	echo '<li> Remote LETS code is de letscode waarmee deze installatie bij de andere groep bekend is, deze moet aan de andere kant aangemaakt zijn.</li>';
	echo '<li> URL is de weblocatie van de andere installatie';
	echo '<li> Preshared Key is een gedeelde sleutel waarmee interlets transacties ondertekend worden.  Deze moet identiek zijn aan de preshared key voor de lets-rekening van deze installatie aan de andere kant</li>';
	echo '</ul>';
	echo '</div>';

	echo '<div class="panel panel-default"><div class="panel-heading">';
	echo '<p>Verbindingen met eLAS-Heroku op deze server.</p>';
	echo '</div>';
	echo '<ul>';
	echo '<li>Alle eLAS-Heroku installaties bevinden zich op dezelfde server (zie onder voor lijst). </li>';
	echo '<li>Met deze letsgroepen kan op een vereenvoudigde manier verbinding gelegd worden zonder het uitwisselen van apikeys, preshared keys en remote letscodes.</li>';
	echo '<li>Voor het leggen van een verbinding, maak eerst een letsgroep aan door op \'Creëer\' in kolom \'lok.groep\' onderaan te klikken en vervolgens toevoegen. Dan, klik op \'Creëer\' in \'Lok.account\', ';
	echo 'Vul een postcode in en klik op \'toevoegen\'. Nu de letsgroep en het interlets account aangemaakt zijn wil dat zeggen dat jouw groep toestemming geeft aan de andere groep om te interletsen. Wanneer ';
	echo 'de andere groep op dezelfde wijze een letsgroep en interlets account aanmaakt is de verbinding compleet. ';
	echo 'In alle vier kolommen (lok.groep, lok.account, rem.groep, rem.account) zie je dan <span class="btn btn-success btn-xs">OK</span>.</li>';
	echo '</ul>';
	echo '</div>';

	list($schemas, $domains) = get_schemas_domains(true);

	$loc_url_ary = $loc_group_ary = $loc_account_ary = array();
	$rem_group_ary =  $rem_account_ary = array();

	$letsgroups = $db->executeQuery('select localletscode, url, id
		from letsgroups
		where url in (?)',
		array(array_values($domains)),
		array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

	foreach ($letsgroups as $l)
	{
		$loc_letscode_ary[] = $l['localletscode'];
		$loc_group_ary[$l['url']] = $l;
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

	foreach ($schemas as $d => $s)
	{
		$rem_group = $db->fetchAssoc('select localletscode, url, id
			from ' . $s . '.letsgroups
			where url = ?', array($base_url));

		if ($rem_group)
		{
			$rem_group_ary[$d] = $rem_group;

			if ($rem_group['localletscode'])
			{
				$rem_account = $db->fetchAssoc('select id, letscode, status, accountrole
					from ' . $s . '.users where letscode = ?', array($rem_group['localletscode']));

				if ($rem_account)
				{
					$rem_account_ary[$d] = $rem_account;
				}
			}
		}
	}

	echo '<div class="panel panel-warning">';
	echo '<div class="panel-heading">';

	echo '<button class="btn btn-default" title="Toon letsgroepen op deze server" data-toggle="collapse" ';
	echo 'data-target="#server">';
	echo '<i class="fa fa-question"></i>';
	echo ' Letsgroepen op deze server</button>';
	echo '</div>';
	echo '<div class=" collapse" id="server">';

	echo '<table class="table table-bordered table-hover table-striped">';
	echo '<thead>';
	echo '<tr>';
	echo '<th data-sort-initial="true" data-hide="phone, tablet">tag</th>';
	echo '<th>groepsnaam</th>';
	echo '<th data-hide="phone, tablet">url</th>';
	echo '<th>lok.groep</th>';
	echo '<th>lok.account</th>';
	echo '<th>rem.groep</th>';
	echo '<th>rem.account</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($schemas as $d => $s)
	{
		echo '<tr>';
		echo '<td>';
		echo readconfigfromschema('systemtag', $s);
		echo '</td>';
		echo '<td>';
		echo readconfigfromschema('systemname', $s);
		echo '</td>';
		echo '<td>';
		echo $d;
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
			if (is_array($loc_group =  $loc_group_ary[$d]))
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
			if ($rem_group_ary[$d])
			{
				echo '<span class="btn btn-success btn-xs">OK</span>';
			}
			else
			{
				echo '<i class="fa fa-times text-danger"></i>';
			}
			echo '</td>';
			echo '<td>';
			if ($rem_acc = $rem_account_ary[$d])
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
	echo '</div>';
	echo '</div></div>';
}

function cancel($id = null)
{
	$id = ($id) ? 'id=' . $id : '';

	header('Location: ' . generate_url('interlets', $id));
	exit;
}
