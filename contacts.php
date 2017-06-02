<?php

$uid = $_GET['uid'] ?? false;
$abbrev = $_GET['abbrev'] ?? '';
$q = $_GET['q'] ?? '';
$letscode = $_GET['letscode'] ?? '';
$access = $_GET['access'] ?? 'all';
$ustatus = $_GET['ustatus'] ?? 'all';

$orderby = $_GET['orderby'] ?? 'c.id';
$asc = $_GET['asc'] ?? 0;
$limit = $_GET['limit'] ?? 25;
$start = $_GET['start'] ?? 0;

$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = $_GET['add'] ?? false;
$inline = isset($_GET['inline']) ? true : false;
$submit = isset($_POST['zend']) ? true : false;

$page_access = ($del || $add || $edit) ? 'user' : 'guest';
$page_access = ($abbrev || !$uid) ? 'admin' : $page_access;

require_once __DIR__ . '/include/web.php';

if ($del)
{
	if (!($user_id = $app['db']->fetchColumn('select c.id_user from contact c where c.id = ?', array($del))))
	{
		$app['alert']->error('Het contact bestaat niet.');
		cancel();
	}

	if ($uid && $uid != $user_id)
	{
		$app['alert']->error('uid in url is niet de eigenaar van contact.');
		cancel();
	}

	$user_id = ($uid) ? $uid : $user_id;

	$s_owner = (!$s_guest && $s_group_self && $user_id == $s_id && $user_id) ? true : false;

	if (!($s_admin || $s_owner))
	{
		$app['alert']->error('Je hebt geen rechten om het contact te verwijderen.');
		cancel($uid);
	}

	$contact = $app['db']->fetchAssoc('select c.*, tc.abbrev
		from contact c, type_contact tc
		where c.id = ?
			and tc.id = c.id_type_contact', array($del));

	$owner = $app['user_cache']->get($contact['id_user']);

	if ($contact['abbrev'] == 'mail' && ($owner['status'] == 1 || $owner['status'] == 2))
	{
		if ($app['db']->fetchColumn('select count(c.*)
			from contact c, type_contact tc
			where c.id_type_contact = tc.id
				and c.id_user = ?
				and tc.abbrev = \'mail\'', array($user_id)) == 1)
		{
			$err = ($s_owner) ? 'je enige email adres' : 'het enige email adres van een actieve gebruiker';
			$app['alert']->warning('Waarschuwing: dit is ' . $err);
			//cancel($uid);
		}
	}

	if ($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			cancel($uid);
		}

		if ($app['db']->delete('contact', array('id' => $del)))
		{
			$app['alert']->success('Contact verwijderd.');
		}
		else
		{
			$app['alert']->error('Fout bij verwijderen van het contact.');
		}
		cancel($uid);
	}

	$contact = $app['db']->fetchAssoc('select tc.abbrev, c.value, c.comments, c.flag_public
		from type_contact tc, contact c
		where c.id_type_contact = tc.id
			and c.id = ?', array($del));

	$h1 = 'Contact verwijderen?';

	include __DIR__ . '/include/header.php';

	echo '<br>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<dl>';
	if (!$s_owner)
	{
		echo '<dt>Gebruiker</dt>';
		echo '<dd>' . link_user($user_id) . '</dd>';
	}
	echo '<dt>Type</dt>';
	echo '<dd>' . $contact['abbrev'] . '</dd>';
	echo '<dt>Waarde</dt>';
	echo '<dd>' . $contact['value'] . '</dd>';
	echo '<dt>Commentaar</dt>';
	echo '<dd>';
	echo ($contact['comments']) ?: '<i class="fa fa-times"></i>';
	echo '</dd>';
	echo '<dt>Zichtbaarheid</dt>';
	echo '<dd>' . $app['access_control']->get_label($contact['flag_public']) . '</dd>';
	echo '</dl>';

	echo '<form method="post" class="form-horizontal">';

	if ($uid)
	{
		echo '<input type="hidden" name="uid" value="' . $uid . '">';
		echo aphp('users', ['id' => $uid], 'Annuleren', 'btn btn-default');
	}
	else
	{
		echo aphp('contacts', [], 'Annuleren', 'btn btn-default');
	}

	echo '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	$app['form_token']->generate();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

if ($edit || $add)
{
	if ($edit)
	{
		if (!($user_id = $app['db']->fetchColumn('select id_user from contact where id = ?', [$edit])))
		{
			$app['alert']->error('Dit contact heeft geen eigenaar of bestaat niet.');
			cancel();
		}

		if ($uid && $uid != $user_id)
		{
			$app['alert']->error('uid in url is niet de eigenaar van contact.');
			cancel();
		}
	}
	else
	{
		$user_id = false;
	}

	$user_id = ($uid) ? $uid : $user_id;

	$s_owner = (!$s_guest && $s_group_self && $user_id == $s_id && $user_id) ? true : false;

	if (!($s_admin || $s_owner))
	{
		$err = ($edit) ? 'dit contact aan te passen.' : 'een contact toe te voegen voor deze gebruiker.';
		$app['alert']->error('Je hebt geen rechten om ' . $err);
		cancel($uid);
	}

	if($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$errors[] = $error_token;
		}

		if ($s_admin && $add && !$uid)
		{
			$letscode = $_POST['letscode'];
			list($letscode) = explode(' ', trim($letscode));

			$user_id = $app['db']->fetchColumn('select id from users where letscode = \'' . $letscode . '\'');

			if ($user_id)
			{
				$letscode = link_user($user_id, false, false);
			}
			else
			{
				$errors[] = 'Ongeldige letscode.';
			}
		}

		$contact = array(
			'id_type_contact'		=> $_POST['id_type_contact'],
			'value'					=> trim($_POST['value']),
			'comments' 				=> trim($_POST['comments']),
			'flag_public'			=> $app['access_control']->get_post_value(), //$_POST['flag_public'],
			'id_user'				=> $user_id,
		);

		$mail_type_id = $app['db']->fetchColumn('select id from type_contact where abbrev = \'mail\'');

		if ($contact['id_type_contact'] == $mail_type_id && !filter_var($contact['value'], FILTER_VALIDATE_EMAIL))
		{
			$errors[] = 'Geen geldig email adres';
		}

		if (!$contact['value'])
		{
			$errors[] = 'Vul waarde in!';
		}

		if (strlen($contact['value']) > 130)
		{
			$errors[] = 'De waarde mag maximaal 130 tekens lang zijn.';
		}

		if (strlen($contact['comments']) > 50)
		{
			$errors[] = 'Commentaar mag maximaal 50 tekens lang zijn.';
		}

		if(!$app['db']->fetchColumn('SELECT abbrev FROM type_contact WHERE id = ?', array($contact['id_type_contact'])))
		{
			$errors[] = 'Contacttype bestaat niet!';
		}

		$access_error = $app['access_control']->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}

		if ($edit)
		{
			$count_mail = $app['db']->fetchColumn('select count(*)
				from contact
				where id_user = ?
					and id_type_contact = ?',
				array($user_id, $mail_type_id));

			$mail_id = $app['db']->fetchColumn('select id
				from contact
				where id_user = ?
					and id_type_contact = ?',
				array($user_id, $mail_type_id));

			if ($edit == $mail_id && $count_mail == 1 && $contact['id_type_contact'] != $mail_type_id)
			{
				$app['alert']->warning('Waarschuwing: de gebruiker heeft geen mailadres.');
			}
		}

		if ($contact['id_type_contact'] == $mail_type_id)
		{
			$mail_count = $app['db']->fetchColumn('select count(c.*)
				from contact c, type_contact tc, users u
				where c.id_type_contact = tc.id
					and tc.abbrev = \'mail\'
					and c.id_user = u.id
					and u.status in (1, 2)
					and u.id <> ?
					and c.value = ?', array($user_id, $contact['value']));

			if ($mail_count && $s_admin)
			{
				$warning = 'Omdat deze gebruikers niet meer een uniek email adres hebben zullen zij ';
				$warning .= 'niet meer zelf hun paswoord kunnnen resetten of kunnen inloggen met ';
				$warning .= 'email adres. Zie ' . aphp('status', [], 'Status');

				if ($mail_count == 1)
				{
					$warning = 'Waarschuwing: email adres ' . $mailadr . ' bestaat al onder de actieve gebruikers. ' . $warning;
					$app['alert']->warning($warning);
				}
				else if ($mail_count > 1)
				{
					$warning = 'Waarschuwing: email adres ' . $mailadr . ' bestaat al ' . $mail_count . ' maal onder de actieve gebruikers. ' . $warning;
					$app['alert']->warning($warning);
				}
			}
			else if ($mail_count)
			{
				$errors[] = 'Dit mailadres komt reeds voor onder de actieve gebruikers.';
			}

		}

		if(!count($errors))
		{
			if ($edit)
			{
				if ($app['db']->update('contact', $contact, array('id' => $edit)))
				{
					$app['alert']->success('Contact aangepast.');
					cancel($uid);
				}
				else
				{
					$app['alert']->error('Fout bij het opslaan');
				}
			}
			else
			{
				if ($app['db']->insert('contact', $contact))
				{
					$app['alert']->success('Contact opgeslagen.');
					cancel($uid);
				}
				else
				{
					$app['alert']->error('Fout bij het opslaan');
				}
			}
		}
		else
		{
			$app['alert']->error($errors);
		}
	}
	else if ($edit)
	{
		$contact = $app['db']->fetchAssoc('select * from contact where id = ?', array($edit));
	}
	else if ($add)
	{
		$contact = array(
			'value'				=> '',
			'comments'			=> '',
			'flag_public'		=> false,
		);
	}

	$tc = [];

	$rs = $app['db']->prepare('SELECT id, name FROM type_contact');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$tc[$row['id']] = $row['name'];

		if (isset($contact['id_type_contact']))
		{
			continue;
		}

		$contact['id_type_contact'] = $row['id'];
	}

	if ($s_admin && $add && !$uid)
	{
		$app['assets']->add(['typeahead', 'typeahead.js']);
	}

	$h1 = ($edit) ? 'Contact aanpassen' : 'Contact toevoegen';
	$h1 .= (($s_owner && !$s_admin) || ($s_admin && $add && !$uid)) ? '' : ' voor ' . link_user($user_id);

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	if ($s_admin && $add && !$uid)
	{
		$typeahead_ary = array('users_active', 'users_inactive', 'users_ip', 'users_im', 'users_extern');

		echo '<div class="form-group">';
		echo '<label for="letscode" class="col-sm-2 control-label">Voor</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="letscode" name="letscode" ';
		echo 'data-typeahead="' . $app['typeahead']->get($typeahead_ary) . '" ';
		echo 'data-newuserdays="' . $app['config']->get('newuserdays') . '" ';
		echo 'placeholder="letscode" ';
		echo 'value="' . $letscode . '" required>';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<label for="id_type_contact" class="col-sm-2 control-label">Type</label>';
	echo '<div class="col-sm-10">';
	echo '<select name="id_type_contact" id="id_type_contact" class="form-control" required>';
	render_select_options($tc, $contact['id_type_contact']);
	echo "</select>";
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="value" class="col-sm-2 control-label">Waarde</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="value" name="value" ';
	echo 'value="' . $contact['value'] . '" required maxlength="130">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="comments" class="col-sm-2 control-label">Commentaar</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="comments" name="comments" ';
	echo 'value="' . $contact['comments'] . '" maxlength="50">';
	echo '</div>';
	echo '</div>';

	echo $app['access_control']->get_radio_buttons(false, $contact['flag_public']);

	if ($uid)
	{
		echo '<input type="hidden" name="uid" value="' . $uid . '">';
		echo aphp('users', ['id' => $uid], 'Annuleren', 'btn btn-default');
	}
	else
	{
		echo aphp('contacts', [], 'Annuleren', 'btn btn-default');
	}

	echo '&nbsp;';

	if ($add)
	{
		echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-success">';
	}
	else
	{
		echo '<input type="submit" value="Aanpassen" name="zend" class="btn btn-primary">';
	}
	$app['form_token']->generate();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * show contacts of a user
 */

if ($uid)
{
	$s_owner = (!$s_guest && $s_group_self && $uid == $s_id && $uid) ? true : false;

	$contacts = $app['db']->fetchAll('select c.*, tc.abbrev
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?', array($uid));

	$user = $app['user_cache']->get($uid);

	if ($s_admin || $s_owner)
	{
		$top_buttons .= aphp('contacts', ['add' => 1, 'uid' => $uid], 'Toevoegen', 'btn btn-success', 'Contact toevoegen', 'plus', true);
	}

	if (!$inline)
	{
		$h1 = ($s_owner) ? 'Mijn contacten' : 'Contacten Gebruiker ' . link_user($user);
		$fa = 'map-marker';

		include __DIR__ . '/include/header.php';
		echo '<br>';
	}
	else
	{
		echo '<div class="row">';
		echo '<div class="col-md-12">';

		echo '<h3><i class="fa fa-map-marker"></i> Contactinfo van ' . link_user($user) . ' ';
		echo $top_buttons;
		echo '</h3>';
	}

	if (!count($contacts))
	{
		echo '<br>';
		echo '<div class="panel panel-danger">';
		echo '<div class="panel-body">';
		echo '<p>Er is geen contactinfo voor deze gebruiker.</p>';
		echo '</div></div>';

		if (!$inline)
		{
			include __DIR__ . '/include/footer.php';
		}
		exit;
	}

	echo '<div class="panel panel-danger">';
	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable" ';
	echo 'data-sort="false">';

	echo '<thead>';
	echo '<tr>';

	echo '<th>Type</th>';
	echo '<th>Waarde</th>';
	echo '<th data-hide="phone, tablet">Commentaar</th>';

	if ($s_admin || $s_owner)
	{
		echo '<th data-hide="phone, tablet">Zichtbaarheid</th>';
		echo '<th data-sort-ignore="true" data-hide="phone, tablet">Verwijderen</th>';
	}

	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach ($contacts as $c)
	{
		echo '<tr>';
		echo '<td>' . $c['abbrev'] . '</td>';

		if (($c['flag_public'] < $access_level) && !$s_owner)
		{
			echo '<td><span class="btn btn-default btn-xs">verborgen</span></td>';
			echo '<td><span class="btn btn-default btn-xs">verborgen</span></td>';
		}
		else if ($s_owner || $s_admin)
		{
			echo '<td>';
			echo  aphp('contacts', ['edit' => $c['id'], 'uid' => $uid], $c['value']);
			if ($c['abbrev'] == 'adr' && !$s_elas_guest && !$s_master)
			{
				echo $app['distance']->set_from_geo('', $s_id, $s_schema)
					->set_to_geo(trim($c['value']))
					->calc()
					->format_parenthesis();
			}
			echo '</td>';
			echo '<td>' . aphp('contacts', ['edit' => $c['id'], 'uid' => $uid], $c['comments']) . '</td>';
		}
		else if ($c['abbrev'] == 'mail')
		{
			echo '<td><a href="mailto:' . $c['value'] . '">' . $c['value'] . '</a></td>';
			echo '<td>' . htmlspecialchars($c['comments'], ENT_QUOTES) . '</td>';
		}
		else
		{
			echo '<td>';
			echo htmlspecialchars($c['value'], ENT_QUOTES);
			if ($c['abbrev'] == 'adr' && !$s_elas_guest && !$s_master)
			{
				echo $app['distance']->set_from_geo('', $s_id, $s_schema)
					->set_to_geo(trim($c['value']))
					->calc()
					->format_parenthesis();
			}
			echo '</td>';
			echo '<td>' . htmlspecialchars($c['comments'], ENT_QUOTES) . '</td>';
		}

		if ($s_admin || $s_owner)
		{
			echo '<td>' . $app['access_control']->get_label($c['flag_public']) . '</td>';

			echo '<td>';
			echo aphp('contacts', ['del' => $c['id'], 'uid' => $uid], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
			echo '</td>';
		}
		echo '</tr>';
	}

	echo '</tbody>';

	echo '</table>';

	if ($app['distance']->get_to_geo() && $inline)
	{
		echo '<div class="panel-footer">';
		echo '<div class="user_map" id="map" data-markers="';
		echo $app['distance']->get_to_data();
		echo '" ';
		echo 'data-token="' . $app['mapbox_token'] . '"></div>';
		echo '</div>';
	}

	echo '</div></div>';

	echo '</div>';

	if ($inline)
	{
		exit;
	}

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 *
 */

if (!$s_admin)
{
	$app['alert']->error('Je hebt geen toegang tot deze pagina.');
	redirect_default_page();
}

$s_owner = (!$s_guest && $s_group_self && $s_id == $uid && $s_id && $uid) ? true : false;

$params = array(
	'orderby'	=> $orderby,
	'asc'		=> $asc,
	'limit'		=> $limit,
	'start'		=> $start,
);

$params_sql = $where_sql = array();

if ($uid)
{
	$user = $app['user_cache']->get($uid);

	$where_sql[] = 'c.id_user = ?';
	$params_sql[] = $uid;
	$params['uid'] = $uid;

	$letscode = link_user($user, false, false);
}

if (!$uid)
{
	if ($letscode)
	{
		list($letscode) = explode(' ', trim($letscode));

		$fuid = $app['db']->fetchColumn('select id from users where letscode = \'' . $letscode . '\'');

		if ($fuid)
		{
			$where_sql[] = 'c.id_user = ?';
			$params_sql[] = $fuid;

			$letscode = link_user($fuid, false, false);
		}
		else
		{
			$where_sql[] = '1 = 2';
		}

		$params['letscode'] = $letscode;
	}
}

if ($q)
{
	$where_sql[] = '(c.value ilike ? or c.comments ilike ?)';
	$params_sql[] = '%' . $q . '%';
	$params_sql[] = '%' . $q . '%';
	$params['q'] = $q;
}

if ($abbrev)
{
	$where_sql[] = 'tc.abbrev = ?';
	$params_sql[] = $abbrev;
	$params['abbrev'] = $abbrev;
}

if ($access != 'all')
{
	switch ($access)
	{
		case 'admin':
			$acc = 0;
			break;
		case 'users':
			$acc = 1;
			break;
		case 'interlets':
			$acc = 2;
			break;
		default:
			$access = 'all';
			break;
	}

	if ($access != 'all')
	{
		$where_sql[] = 'c.flag_public = ?';
		$params_sql[] = $acc;
		$params['access'] = $acc;
	}
}

switch ($ustatus)
{
	case 'new':
		$where_sql[] = 'u.adate > ? and u.status = 1';
		$params_sql[] = gmdate('Y-m-d H:i:s', $newusertreshold);
		$params['ustatus'] = 'new';
		break;
	case 'leaving':
		$where_sql[] = 'u.status = 2';
		$params['ustatus'] = 'leaving';
		break;
	case 'active':
		$where_sql[] = 'u.status in (1, 2)';
		$params['ustatus'] = 'active';
		break;
	case 'inactive':
		$where_sql[] = 'u.status = 0';
		$params['ustatus'] = 'inactive';
		break;
	case 'ip':
		$where_sql[] = 'u.status = 5';
		$params['ustatus'] = 'ip';
		break;
	case 'im':
		$where_sql[] = 'u.status = 6';
		$params['ustatus'] = 'im';
		break;
	case 'extern':
		$where_sql[] = 'u.status = 7';
		$params['ustatus'] = 'extern';
		break;
	default:
		break;
}

$user_table_sql = '';

if ($ustatus != 'all' || $orderby == 'u.letscode')
{
	$user_table_sql = ', users u ';
	$where_sql[] = 'u.id = c.id_user';
}

if (count($where_sql))
{
	$where_sql = ' and ' . implode(' and ', $where_sql) . ' ';
}
else
{
	$where_sql = '';
}

$query = 'select c.*, tc.abbrev
	from contact c, type_contact tc' . $user_table_sql . '
	where c.id_type_contact = tc.id' . $where_sql;

$row_count = $app['db']->fetchColumn('select count(c.*)
	from contact c, type_contact tc' . $user_table_sql . '
	where c.id_type_contact = tc.id' . $where_sql, $params_sql);

$query .= ' order by ' . $orderby . ' ';
$query .= ($asc) ? 'asc ' : 'desc ';
$query .= ' limit ' . $limit . ' offset ' . $start;

$contacts = $app['db']->fetchAll($query, $params_sql);

$app['pagination']->init('contacts', $row_count, $params, $inline);

$asc_preset_ary = array(
	'asc'	=> 0,
	'indicator' => '',
);

$tableheader_ary = array(
	'tc.abbrev' => array_merge($asc_preset_ary, array(
		'lbl' => 'Type')),
	'c.value' => array_merge($asc_preset_ary, array(
		'lbl' => 'Waarde')),
	'u.letscode'	=> array_merge($asc_preset_ary, array(
		'lbl' 		=> 'Gebruiker')),
	'c.comments'	=> array_merge($asc_preset_ary, array(
		'lbl' 		=> 'Commentaar',
		'data_hide'	=> 'phone,tablet')),
	'c.flag_public' => array_merge($asc_preset_ary, array(
		'lbl' 		=> 'Zichtbaar',
		'data_hide'	=> 'phone, tablet')),
	'del' => array_merge($asc_preset_ary, array(
		'lbl' 		=> 'Verwijderen',
		'data_hide'	=> 'phone, tablet',
		'no_sort'	=> true)),
);

$tableheader_ary[$orderby]['asc'] = ($asc) ? 0 : 1;
$tableheader_ary[$orderby]['indicator'] = ($asc) ? '-asc' : '-desc';

unset($tableheader_ary['c.id']);

$abbrev_ary = array();

$rs = $app['db']->prepare('select abbrev from type_contact');
$rs->execute();
while($row = $rs->fetch())
{
	$abbrev_ary[$row['abbrev']] = $row['abbrev'];
}

$top_right .= '<a href="#" class="csv">';
$top_right .= '<i class="fa fa-file"></i>';
$top_right .= '&nbsp;csv</a>';

$top_buttons .= aphp('contacts', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Contact toevoegen', 'plus', true);

$panel_collapse = ($q || $abbrev || $access != 'all' || $letscode || $ustatus != 'all') ? false : true;
$filtered = ($panel_collapse) ? false : true;

$app['assets']->add(['csv.js', 'typeahead', 'typeahead.js']);

$h1 = 'Contacten';
$h1 .= ($filtered) ? ' <small>gefilterd</small>' : '';
$h1 .= '<div class="pull-right">';
$h1 .= '&nbsp;<button class="btn btn-default hidden-xs" title="Filters" ';
$h1 .= 'data-toggle="collapse" data-target="#filters"';
$h1 .= '><i class="fa fa-caret-down"></i><span class="hidden-xs hidden-sm"> Filters</span></button>';
$h1 .= '</div>';

$fa = 'map-marker';

include __DIR__ . '/include/header.php';

echo '<div id="filters" class="panel panel-info';
echo ($panel_collapse) ? ' collapse' : '';
echo '">';

echo '<div class="panel-heading">';

echo '<form method="get" class="form-horizontal">';

echo '<div class="row">';

echo '<div class="col-sm-4">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="q" value="' . $q . '" name="q" placeholder="Zoeken">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-4">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo 'Type';
echo '</span>';
echo '<select class="form-control" id="abbrev" name="abbrev">';
render_select_options(array_merge(array('' => ''), $abbrev_ary), $abbrev);
echo '</select>';
echo '</div>';
echo '</div>';

$access_options = [
	'all'		=> '',
	'admin'		=> 'admin',
	'users'		=> 'leden',
	'interlets'	=> 'interlets',
];

if (!$app['config']->get('template_lets') || !$app['config']->get('interlets_en'))
{
	unset($access_options['interlets']);
}

echo '<div class="col-sm-4">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo 'Zichtbaar';
echo '</span>';
echo '<select class="form-control" id="access" name="access">';
render_select_options($access_options, $access);
echo '</select>';
echo '</div>';
echo '</div>';

echo '</div>';

echo '<div class="row">';

$user_status_options = array(
	'all'		=> 'Alle',
	'active'	=> 'Actief',
	'new'		=> 'Enkel instappers',
	'leaving'	=> 'Enkel uitstappers',
	'inactive'	=> 'Inactief',
	'ip'		=> 'Info-pakket',
	'im'		=> 'Info-moment',
	'extern'	=> 'Extern',
);

echo '<div class="col-sm-5">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo 'Status ';
echo '<i class="fa fa-user"></i>';
echo '</span>';
echo '<select class="form-control" id="ustatus" name="ustatus">';
render_select_options($user_status_options, $ustatus);
echo '</select>';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-5">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon" id="fcode_addon">Van ';
echo '<span class="fa fa-user"></span></span>';

$typeahead_name_ary = array('users_active', 'users_inactive', 'users_ip', 'users_im', 'users_extern');

echo '<input type="text" class="form-control" ';
echo 'aria-describedby="letscode_addon" ';
echo 'data-typeahead="' . $app['typeahead']->get($typeahead_name_ary) . '" ';
echo 'data-newuserdays="' . $app['config']->get('newuserdays') . '" ';
echo 'name="letscode" id="letscode" placeholder="letscode" ';
echo 'value="' . $letscode . '">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-2">';
echo '<input type="submit" value="Toon" class="btn btn-default btn-block">';
echo '</div>';

echo '</div>';

$params_form = $params;
unset($params_form['access'], $params_form['q'], $params_form['abbrev']);
unset($params_form['letscode'], $params_form['ustatus']);
unset($params_form['start']);

$params_form['r'] = 'admin';
$params_form['u'] = $s_id;

foreach ($params_form as $name => $value)
{
	if (isset($value))
	{
		echo '<input name="' . $name . '" value="' . $value . '" type="hidden">';
	}
}

echo '</form>';

echo '</div>';
echo '</div>';

$app['pagination']->render();

if (!count($contacts))
{
	echo '<br>';
	echo '<div class="panel panel-danger">';
	echo '<div class="panel-body">';
	echo '<p>Er zijn geen resultaten.</p>';
	echo '</div></div>';

	$app['pagination']->render();

	include __DIR__ . '/include/footer.php';
	exit;
}

echo '<div class="panel panel-danger">';
echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable csv" ';
echo 'data-sort="false">';

echo '<thead>';
echo '<tr>';

$th_params = $params;

foreach ($tableheader_ary as $key_orderby => $data)
{
	echo '<th';
	echo (isset($data['data_hide'])) ? ' data-hide="' . $data['data_hide'] . '"' : '';
	echo '>';
	if (isset($data['no_sort']))
	{
		echo $data['lbl'];
	}
	else
	{
		$th_params['orderby'] = $key_orderby;
		$th_params['asc'] = $data['asc'];

		echo '<a href="' . generate_url('contacts', $th_params) . '">';
		echo $data['lbl'] . '&nbsp;<i class="fa fa-sort' . $data['indicator'] . '"></i>';
		echo '</a>';
	}
	echo '</th>';
}

echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach ($contacts as $c)
{
	echo '<tr>';
	echo '<td>' . $c['abbrev'] . '</td>';

	echo '<td>' . aphp('contacts', ['edit' => $c['id']], $c['value']) . '</td>';
	echo '<td>' . link_user($c['id_user']) . '</td>';
	echo '<td>' . aphp('contacts', ['edit' => $c['id']], $c['comments']) . '</td>';
	echo '<td>' . $app['access_control']->get_label($c['flag_public']) . '</td>';

	echo '<td>';
	echo aphp('contacts', ['del' => $c['id']], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
	echo '</td>';

	echo '</tr>';
}

echo '</tbody>';

echo '</table>';

echo '</div></div>';

$app['pagination']->render();

include __DIR__ . '/include/footer.php';

function cancel($uid = false)
{
	if ($uid)
	{
		header('Location: ' . generate_url('users', ['id' => $uid]));
	}
	else
	{
		header('Location: ' . generate_url('contacts'));
	}
	exit;
}
