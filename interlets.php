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

$post = ($_SERVER['REQUEST_METHOD'] == 'POST') ? true : false;

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

		if ($edit)
		{
			if ($db->update('letsgroups', $group, array('id' => $id)))
			{
				$alert->success('Letsgroep aangepast.');
				cancel($edit);
			}

			$alert->error('Letsgroep niet aangepast.');
		}
		else
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
	echo '<a href="' . $rootpath . 'interlets.php" class="btn btn-default">Annuleren</a>&nbsp;';
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
	echo '<a href="' . $rootpath . 'interlets.php" class="btn btn-default">Annuleren</a>&nbsp;';
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
	$top_buttons = '<a href="' . $rootpath . 'interlets.php?add=1" class="btn btn-success"';
	$top_buttons .= ' title="Letsgroep toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

	$top_buttons .= '<a href="' . $rootpath . 'interlets.php?edit=' . $id . '" class="btn btn-primary"';
	$top_buttons .= ' title="Letsgroep aanpassen"><i class="fa fa-pencil"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

	$top_buttons .= '<a href="' . $rootpath . 'interlets.php?del=' . $id . '" class="btn btn-danger"';
	$top_buttons .= ' title="Letsgroep verwijderen">';
	$top_buttons .= '<i class="fa fa-times"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';

	$top_buttons .= '<a href="' . $rootpath . 'interlets.php" class="btn btn-default"';
	$top_buttons .= ' title="Lijst letsgroepen"><i class="fa fa-share-alt"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

	$h1 = $group['groupname'];
	$fa = 'share-alt';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';

	echo '<dl class="dl-horizontal">';
	echo '<dt>eLAS Soap status</dt>';

	echo '<dd><i><div id="statusdiv">';

	$soapurl = $group['elassoapurl'] .'/wsdlelas.php?wsdl';
	$apikey = $group['remoteapikey'];
	$client = new nusoap_client($soapurl, true);
	$err = $client->getError();
	if (!$err) {
		$result = $client->call('getstatus', array('apikey' => $apikey));
		$err = $client->getError();
			if (!$err) {
			echo $result;
		}
	}
	echo '</div></i>';
	echo '</dd>';

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

	echo '<p><small><i>';
	echo '<ul>';
	echo '<li> API methode bepaalt de connectie naar de andere groep, geldige waarden zijn internal, elassoap en mail (internal is niet van tel in eLAS-Heroku)</li>';
	echo '<li> De API key moet je aanvragen bij de beheerder van de andere installatie, het is een sleutel die je eigen eLAS toelaat om met de andere eLAS te praten</li>';
	echo '<li> Lokale LETS Code is de letscode waarmee de andere groep op deze installatie bekend is, deze gebruiker moet al bestaan</li>';
	echo '<li> Remote LETS code is de letscode waarmee deze installatie bij de andere groep bekend is, deze moet aan de andere kant aangemaakt zijn</li>';
	echo '<li> URL is de weblocatie van de andere installatie';
//	echo '<li> SOAP URL is de locatie voor de communicatie tussen eLAS en het andere systeem, voor een andere eLAS is dat de URL met /soap erachter</li>';
	echo '<li> Preshared Key is een gedeelde sleutel waarmee interlets transacties ondertekend worden.  Deze moet identiek zijn aan de preshared key voor de lets-rekening van deze installatie aan de andere kant</li>';
	echo '</ul></i></small></p>';

	render_schemas_groups();

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/*
 * login
 */
if ($login)
{
	$err_group = $group['groupname'] . ': ';

	if($group['apimethod'] == 'elassoap')
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
	else
	{
		$alert->error($err_group . 'Deze groep draait geen eLAS-soap, kan geen connectie maken');
	}

	echo '<script>setTimeout(function(){location.href = "' . $rootpath . 'interlets.php";}, 1000);</script>';
	exit;
}

/**
 * list
 */
$where = ($s_admin) ? '' : ' where apimethod <> \'internal\'';
$groups = $db->fetchAll('SELECT * FROM letsgroups' . $where);

if ($s_admin)
{
	list($schemas, $domains) = get_schemas_domains(true);

	$letscodes = $groups_domains = $group_schemas = array();

	foreach ($groups as $key => $g)
	{
		$letscodes[] = $g['localletscode'];

		if ($schemas[$g['url']])
		{
			$groups[$key]['server'] = true;
//			$groups_domains[$domain] = $sch;
//			$groups_schemas[$sch] = $domain;
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


	$top_buttons = '<a href="' . $rootpath . 'interlets.php?add=1" class="btn btn-success"';
	$top_buttons .= ' title="Groep toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
}

$h1 = 'InterLETS groepen';
$fa = 'share-alt';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-primary">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo ($s_admin) ? '<th data-sort-initial="true">letscode</th>' : '';
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
	if($g['apimethod'] == 'elassoap')
	{
		$a1 = '<a href="' . $rootpath . 'interlets.php?login=' . $g['id'] . '" ';
		$a1 .= 'title="login als gast op deze letsgroep">';
		$a2 = '</a>';
	}
	else
	{
		$a1 = $a2 = '';
	}
	echo '<tr>';
	if ($s_admin)
	{
		echo '<td>';
		$user = $users_letscode[$g['localletscode']];
		if ($user)
		{
			echo '<a href="' . $rootpath . 'users.php?id=' . $user['id'] . '" ';
			echo 'class="btn btn-default btn-xs" title="Ga naar het interlets account">';
			echo $g['localletscode'] . '</a>';
			if (!in_array($user['status'], array(1, 2, 7)))
			{
				echo ' <a title="Het interlets-account heeft een ongeldige status. De status moet ';
				echo 'van het type extern, actief of uitstapper zijn." ';
				echo 'href="' . $rootpath . 'users.php?edit=' . $user['id'] . '" '; 
				echo 'class="btn btn-default btn-xs text-danger">';
				echo '<span class="fa fa-exclamation-triangle"></span> Status!</a';
			}
			if ($user['accountrole'] != 'interlets')
			{
				echo ' <a title="Het interlets-account heeft een ongeldige rol. De rol moet ';
				echo 'van het type interlets zijn." ';
				echo 'href="' . $rootpath . 'users.php?edit=' . $user['id'] . '" ';
				echo 'class="btn btn-default btn-xs text-danger">';
				echo '<span class="fa fa-exclamation-triangle"></span> Rol!</a>';
			}
		}
		else
		{
			echo $g['localletscode'];

			if ($g['apimethod'] != 'internal' && !$user)
			{
				echo ' <a title="Creëer een interlets-account met gelijke letscode en status extern." ';
				echo 'href="' . $rootpath . 'users.php?add=1&interlets=' . $g['localletscode'] . '" ';
				echo 'class="btn btn-default btn-xs text-danger"><span class="fa fa-exclamation-triangle"></span> ';
				echo ' Account!</span>';
			}
		}
		echo '</td>';
	}
	echo '<td>' . $a1 . $g['groupname'] . $a2 . '</td>';
	echo '<td>' . $a1 . $redis->get($g['url'] . '_active_user_count') . $a2 . '</td>';
	if ($s_admin)
	{
		echo '<td><a href="' . $rootpath . 'interlets.php?id=' . $g['id'] . '" class="btn btn-default btn-xs">';
		echo 'Instellingen</a>';
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
	global $schema, $db;

	list($schemas, $domains) = get_schemas_domains(true);

	$loc_url_ary = $loc_letscode_ary = array();

	$letsgroups = $db->executeQuery('select localletscode, url, id
		from letsgroups
		where url in (?)',
		array(array_values($domains)),
		array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

	foreach ($letsgroups as $l)
	{
		$loc_letscode_ary[] = $l['localletscode'];
		$loc_url_ary[$l['url']] = array(
			'id'		=> $l['id'],
			'letscode'	=> $l['localletscode'],
		);
	}

	$interlets_accounts = $db->executeQuery('select id, letscode, accountrole, status
		from users
		where status in (1, 2, 7)
			and accountrole = \'interlets\'
			and letscode in (?)',
		array($loc_letscode_ary),
		array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

	foreach ($interlets_accounts as $u)
	{

	}


	echo '<p><small><i><ul>';
	echo '<li>In eLAS-Heroku is het niet langer nodig een \'internal\' groep aan te maken ';
	echo 'voor de eigen groep zoals dat in eLAS het geval is.</li>';
	echo '</ul></i></small></p>';

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
	echo '<th data-sort-initial="true">tag</th>';
	echo '<th>groepsnaam</th>';
	echo '<th>url</th>';
	echo '<th>lok.groep</th>';
	echo '<th>lok.account</th>';
	echo '<th>rem.groep</th>';
	echo '<th>rem.account</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($schemas as $d => $s)
	{
		$url = 'http://' . $d;
		echo '<tr>';
		echo '<td>';
		echo readconfigfromschema('systemtag', $s);
		echo '</td>';
		echo '<td>';
		echo readconfigfromschema('systemname', $s);
		echo '</td>';
		echo '<td>';
		echo $url;
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
			if (is_array($loc_url_ary[$url]))
			{
				$loc = $loc_url_ary[$url];
				echo '<a href="' . $rootpath . 'interlets.php?id=' . $loc['id'] . '" class="btn btn-success btn-xs">';
				echo 'OK';
				echo '</a>';
			}
			else
			{
				echo '<a href="' . $rootpath . 'interlets.php?add=1&add_schema=' . $s . '" ';
				echo 'class="btn btn-default btn-xs">Creëer</a>';
			}
			echo '</td>';
			echo '<td>';
			echo '</td>';
			echo '<td>';
			echo '</td>';
			echo '<td>';
			echo '</td>';
		}

		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table>';
	echo '</div>';
	echo '</div></div>';
}

function cancel($id = null)
{
	global $rootpath;

	$param = ($id) ? '?id=' . $id : '';

	header('Location: ' . $rootpath . 'interlets.php' . $param);
	exit;
}
