<?php

if ($app['s_anonymous'])
{
	exit;
}

$uid = $_GET['uid'] ?? false;
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = $_GET['add'] ?? false;
$submit = isset($_POST['zend']) ? true : false;

$filter = $_GET['f'] ?? [];
$pag = $_GET['p'] ?? [];
$sort = $_GET['sort'] ?? [];

if ($uid)
{
	if (!$app['s_admin'])
	{
		exit;
	}
}
else if ($del || $add || $edit)
{
	if (!($app['s_admin'] || $app['s_user']))
	{
		exit;
	}
}

if ($del)
{
	if (!($user_id = $app['db']->fetchColumn('select c.id_user
		from ' . $app['tschema'] . '.contact c
		where c.id = ?', [$del])))
	{
		$app['alert']->error('Het contact bestaat niet.');
		$app['link']->redirect('contacts', $app['pp_ary'], []);
	}

	if ($uid && $uid != $user_id)
	{
		$app['alert']->error('uid in url is niet de eigenaar van contact.');
		$app['link']->redirect('contacts', $app['pp_ary'], []);
	}

	$user_id = $uid ? $uid : $user_id;

	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $user_id == $app['s_id']
		&& $user_id;

	if (!($app['s_admin'] || $s_owner))
	{
		$app['alert']->error('Je hebt geen rechten om het contact te verwijderen.');
		$app['link']->redirect('users', $app['pp_ary'], ['id' => $uid]);
	}

	$contact = $app['db']->fetchAssoc('select c.*, tc.abbrev
		from ' . $app['tschema'] . '.contact c, ' .
			$app['tschema'] . '.type_contact tc
		where c.id = ?
			and tc.id = c.id_type_contact', [$del]);

	$owner = $app['user_cache']->get($del, $app['tschema']);

	if ($contact['abbrev'] == 'mail' && ($owner['status'] == 1 || $owner['status'] == 2))
	{
		if ($app['db']->fetchColumn('select count(c.*)
			from ' . $app['tschema'] . '.contact c, ' .
				$app['tschema'] . '.type_contact tc
			where c.id_type_contact = tc.id
				and c.id_user = ?
				and tc.abbrev = \'mail\'', [$user_id]) == 1)
		{
			$err = $s_owner ? 'je enige E-mail adres' : 'het enige E-mail adres van een actieve gebruiker';
			$app['alert']->warning('Waarschuwing: dit is ' . $err);
		}
	}

	if ($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			$app['link']->redirect('users', $app['pp_ary'], ['id' => $uid]);
		}

		if ($app['db']->delete($app['tschema'] . '.contact', ['id' => $del]))
		{
			$app['alert']->success('Contact verwijderd.');
		}
		else
		{
			$app['alert']->error('Fout bij verwijderen van het contact.');
		}

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $uid]);
	}

	$contact = $app['db']->fetchAssoc('select tc.abbrev, c.value, c.comments, c.flag_public
		from ' . $app['tschema'] . '.type_contact tc, ' .
			$app['tschema'] . '.contact c
		where c.id_type_contact = tc.id
			and c.id = ?', [$del]);

	$app['heading']->add('Contact verwijderen?');

	include __DIR__ . '/include/header.php';

	echo '<br>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<dl>';

	if (!$s_owner)
	{
		echo '<dt>Gebruiker</dt>';
		echo '<dd>';

		echo $app['account']->link($user_id, $app['pp_ary']);

		echo '</dd>';
	}

	echo '<dt>Type</dt>';
	echo '<dd>';
	echo $contact['abbrev'];
	echo '</dd>';
	echo '<dt>Waarde</dt>';
	echo '<dd>';
	echo $contact['value'];
	echo '</dd>';
	echo '<dt>Commentaar</dt>';
	echo '<dd>';
	echo $contact['comments'] ?: '<i class="fa fa-times"></i>';
	echo '</dd>';
	echo '<dt>Zichtbaarheid</dt>';
	echo '<dd>';
	echo $app['access_control']->get_label($contact['flag_public']);
	echo '</dd>';
	echo '</dl>';

	echo '<form method="post" class="form-horizontal">';

	if ($uid)
	{
		echo '<input type="hidden" name="uid" value="' . $uid . '">';
		echo $app['link']->btn_cancel('users', $app['pp_ary'], ['id' => $uid]);
	}
	else
	{
		echo $app['link']->btn_cancel('contacts', $app['pp_ary'], []);
	}

	echo '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	echo $app['form_token']->get_hidden_input();

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
		if (!($user_id = $app['db']->fetchColumn('select id_user
			from ' . $app['tschema'] . '.contact
			where id = ?', [$edit])))
		{
			$app['alert']->error('Dit contact heeft geen eigenaar
				of bestaat niet.');
			$app['link']->redirect('contacts', $app['pp_ary'], []);
		}

		if ($uid && $uid != $user_id)
		{
			$app['alert']->error('uid in url is niet
				de eigenaar van contact.');
			$app['link']->redirect('contacts', $app['pp_ary'], []);
		}
	}
	else
	{
		$user_id = false;
	}

	$user_id = $uid ? $uid : $user_id;

	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $user_id == $app['s_id']
		&& $user_id;

	if (!($app['s_admin'] || $s_owner))
	{
		$err = $edit
			? 'dit contact aan te passen.'
			: 'een contact toe te voegen voor deze gebruiker.';

		$app['alert']->error('Je hebt geen rechten om ' . $err);
		$app['link']->redirect('users', $app['pp_ary'], ['id' => $uid]);
	}

	if($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$errors[] = $error_token;
		}

		if ($app['s_admin'] && $add && !$uid)
		{
			$letscode = $_POST['letscode'];
			[$letscode] = explode(' ', trim($letscode));

			$user_id = $app['db']->fetchColumn('select id
				from ' . $app['tschema'] . '.users
				where letscode = ?', [$letscode]);

			if ($user_id)
			{
				$letscode = $app['account']->str($user_id, $app['tschema']);
			}
			else
			{
				$errors[] = 'Ongeldige letscode.';
			}
		}

		$contact = [
			'id_type_contact'		=> $_POST['id_type_contact'],
			'value'					=> trim($_POST['value']),
			'comments' 				=> trim($_POST['comments']),
			'flag_public'			=> $app['access_control']->get_post_value(),
			'id_user'				=> $user_id,
		];

		$abbrev_type = $app['db']->fetchColumn('select abbrev
			from ' . $app['tschema'] . '.type_contact
			where id = ?', [$contact['id_type_contact']]);

		if ($abbrev_type === 'mail'
			&& !filter_var($contact['value'], FILTER_VALIDATE_EMAIL))
		{
			$errors[] = 'Geen geldig E-mail adres';
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

		if(!$abbrev_type)
		{
			$errors[] = 'Contacttype bestaat niet!';
		}

		$access_error = $app['access_control']->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}

		$mail_type_id = $app['db']->fetchColumn('select id
			from ' . $app['tschema'] . '.type_contact
			where abbrev = \'mail\'');

		if ($edit)
		{
			$count_mail = $app['db']->fetchColumn('select count(*)
				from ' . $app['tschema'] . '.contact
				where id_user = ?
					and id_type_contact = ?',
				[$user_id, $mail_type_id]);

			$mail_id = $app['db']->fetchColumn('select id
				from ' . $app['tschema'] . '.contact
				where id_user = ?
					and id_type_contact = ?',
				[$user_id, $mail_type_id]);

			if ($edit == $mail_id && $count_mail == 1 && $contact['id_type_contact'] != $mail_type_id)
			{
				$app['alert']->warning('Waarschuwing: de gebruiker heeft
					geen E-mail adres.');
			}
		}

		if ($contact['id_type_contact'] == $mail_type_id)
		{
			$mail_count = $app['db']->fetchColumn('select count(c.*)
				from ' . $app['tschema'] . '.contact c, ' .
					$app['tschema'] . '.type_contact tc, ' .
					$app['tschema'] . '.users u
				where c.id_type_contact = tc.id
					and tc.abbrev = \'mail\'
					and c.id_user = u.id
					and u.status in (1, 2)
					and u.id <> ?
					and c.value = ?', [$user_id, $contact['value']]);

			if ($mail_count && $app['s_admin'])
			{
				$warning = 'Omdat deze gebruikers niet meer ';
				$warning .= 'een uniek E-mail adres hebben zullen zij ';
				$warning .= 'niet meer zelf hun paswoord kunnnen resetten ';
				$warning .= 'of kunnen inloggen met ';
				$warning .= 'E-mail adres. Zie ';
				$warning .= $app['link']->link_no_attr('status',
					$app['pp_ary'], [], 'Status');

				if ($mail_count == 1)
				{
					$warning_2 = 'Waarschuwing: E-mail adres ' . $mailadr;
					$warning_2 .= ' bestaat al onder de actieve gebruikers.';
				}
				else if ($mail_count > 1)
				{
					$warning_2 = 'Waarschuwing: E-mail adres ' . $mailadr;
					$warning_2 .= ' bestaat al ' . $mail_count;
					$warning_2 .= ' maal onder de actieve gebruikers.';
				}

				$app['alert']->warning($warning_2 . ' ' . $warning);
			}
			else if ($mail_count)
			{
				$errors[] = 'Dit E-mail adres komt reeds voor onder
					de actieve gebruikers.';
			}
		}

		if(!count($errors))
		{
			if ($abbrev_type === 'adr')
			{
				$app['queue.geocode']->cond_queue([
					'adr'		=> $contact['value'],
					'uid'		=> $contact['id_user'],
					'schema'	=> $app['tschema'],
				], 0);
			}

			if ($edit)
			{
				if ($app['db']->update($app['tschema'] . '.contact',
					$contact, ['id' => $edit]))
				{
					$app['alert']->success('Contact aangepast.');
					$app['link']->redirect('users', $app['pp_ary'], ['id' => $uid]);
				}
				else
				{
					$app['alert']->error('Fout bij het opslaan');
				}
			}
			else
			{
				if ($app['db']->insert($app['tschema'] . '.contact', $contact))
				{
					$app['alert']->success('Contact opgeslagen.');
					$app['link']->redirect('users', $app['pp_ary'], ['id' => $uid]);
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
		$contact = $app['db']->fetchAssoc('select *
			from ' . $app['tschema'] . '.contact
			where id = ?', [$edit]);
	}
	else if ($add)
	{
		$contact = [
			'value'				=> '',
			'comments'			=> '',
			'flag_public'		=> false,
		];
	}

	$tc = [];

	$rs = $app['db']->prepare('select id, name, abbrev
		from ' . $app['tschema'] . '.type_contact');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$tc[$row['id']] = $row;

		if (isset($contact['id_type_contact']))
		{
			continue;
		}

		$contact['id_type_contact'] = $row['id'];
	}

	if ($app['s_admin'] && $add && !$uid)
	{
		$app['assets']->add(['typeahead', 'typeahead.js']);
	}

	$app['assets']->add(['contacts_edit.js']);

	$contacts_format = [
		'adr'	=> [
			'fa'		=> 'map-marker',
			'lbl'		=> 'Adres',
			'explain'	=> 'Voorbeeldstraat 23, 4520 Voorbeeldgemeente',
		],
		'gsm'	=> [
			'fa'		=> 'mobile',
			'lbl'		=> 'GSM',
		],
		'tel'	=> [
			'fa'		=> 'phone',
			'lbl'		=> 'Telefoon',
		],
		'mail'	=> [
			'fa'		=> 'envelope-o',
			'lbl'		=> 'E-mail',
			'type'		=> 'email',
		],
		'web'	=> [
			'fa'		=> 'link',
			'lbl'		=> 'Website',
			'type'		=> 'url',
		],
	];

	$abbrev = $tc[$contact['id_type_contact']]['abbrev'];

	if ($edit)
	{
		$app['heading']->add('Contact aanpassen');
	}
	else
	{
		$app['heading']->add('Contact toevoegen');
	}


	if (!(($s_owner && !$app['s_admin']) || ($app['s_admin'] && $add && !$uid)))
	{
		$app['heading']->add(' voor ');
		$app['heading']->add($app['account']->link($user_id, $app['pp_ary']));
	}

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	if ($app['s_admin'] && $add && !$uid)
	{
		$typeahead_ary = [];

		foreach (['active', 'inactive', 'ip', 'im', 'extern'] as $t_stat)
		{
			$typeahead_ary[] = [
				'accounts', [
					'status'	=> $t_stat,
					'schema'	=> $app['tschema'],
				],
			];
		}

		echo '<div class="form-group">';
		echo '<label for="letscode" class="control-label">Voor</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon" id="fcode_addon">';
		echo '<span class="fa fa-user"></span></span>';
		echo '<input type="text" class="form-control" id="letscode" name="letscode" ';
		echo 'data-typeahead="';
		echo $app['typeahead']->get($typeahead_ary);
		echo '" ';
		echo 'data-newuserdays="';
		echo $app['config']->get('newuserdays', $app['tschema']);
		echo '" ';
		echo 'placeholder="Account Code" ';
		echo 'value="';
		echo $letscode;
		echo '" required>';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<label for="id_type_contact" class="control-label">Type</label>';
	echo '<select name="id_type_contact" id="id_type_contact" ';
	echo 'class="form-control" required>';

	foreach ($tc as $id => $type)
	{
		echo '<option value="';
		echo $id;
		echo '" ';
		echo 'data-abbrev="';
		echo $type['abbrev'];
		echo '" ';
		echo $id == $contact['id_type_contact'] ? ' selected="selected"' : '';
		echo '>';
		echo $type['name'];
		echo '</option>';
	}

	echo "</select>";
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="value" class="control-label">';
	echo 'Waarde</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon" id="value_addon">';
	echo '<i class="fa fa-';
	echo $contacts_format[$abbrev]['fa'] ?? 'circle-o';
	echo '"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="value" name="value" ';
	echo 'value="';
	echo $contact['value'];
	echo '" required disabled maxlength="130" ';
	echo 'data-contacts-format="';
	echo htmlspecialchars(json_encode($contacts_format));
	echo '">';
	echo '</div>';
	echo '<p id="contact-explain">';

	echo '</p>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="comments" class="control-label">';
	echo 'Commentaar</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-comment-o"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="comments" name="comments" ';
	echo 'value="';
	echo $contact['comments'];
	echo '" maxlength="50">';
	echo '</div>';
	echo '</div>';

	echo $app['access_control']->get_radio_buttons(false, $contact['flag_public']);

	if ($uid)
	{
		echo '<input type="hidden" name="uid" value="' . $uid . '">';
		echo $app['link']->btn_cancel('users', $app['pp_ary'], ['id' => $uid]);
	}
	else
	{
		echo $app['link']->btn_cancel('contacts', $app['pp_ary'], []);
	}

	echo '&nbsp;';

	if ($add)
	{
		echo '<input type="submit" value="Opslaan" ';
		echo 'name="zend" class="btn btn-success">';
	}
	else
	{
		echo '<input type="submit" value="Aanpassen" ';
		echo 'name="zend" class="btn btn-primary">';
	}

	echo $app['form_token']->get_hidden_input();

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
	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $uid == $app['s_id']
		&& $uid;

	$contacts = $app['db']->fetchAll('select c.*, tc.abbrev
		from ' . $app['tschema'] . '.contact c, ' .
			$app['tschema'] . '.type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?', [$uid]);

	$user = $app['user_cache']->get($uid, $app['tschema']);

	if ($app['s_admin'] || $s_owner)
	{
		$app['btn_top']->add('contacts', $app['pp_ary'],
			['add' => 1, 'uid' => $uid], 'Contact toevoegen');
	}

	if (!$app['p_inline'])
	{
		if ($s_owner)
		{
			$app['heading']->add('Mijn contacten');
		}
		else
		{
			$app['heading']->add('Contacten Gebruiker ');
			$app['heading']->add($app['account']->link($uid, $app['tschema']));
		}

		$app['heading']->fa('map-marker');

		include __DIR__ . '/include/header.php';
		echo '<br>';
	}
	else
	{
		echo '<div class="row">';
		echo '<div class="col-md-12">';

		echo '<h3>';
		echo '<i class="fa fa-map-marker"></i>';
		echo ' Contactinfo van ';
		echo $app['account']->link($uid, $app['pp_ary']);
		echo ' ';
		echo $app['btn_top']->get();
		echo '</h3>';
	}

	if (!count($contacts))
	{
		echo '<br>';
		echo '<div class="panel panel-danger">';
		echo '<div class="panel-body">';
		echo '<p>Er is geen contactinfo voor deze gebruiker.</p>';
		echo '</div></div>';

		if (!$app['p_inline'])
		{
			include __DIR__ . '/include/footer.php';
		}
		exit;
	}

	echo '<div class="panel panel-danger">';
	echo '<div class="table-responsive">';
	echo '<table class="table table-hover ';
	echo 'table-striped table-bordered footable" ';
	echo 'data-sort="false">';

	echo '<thead>';
	echo '<tr>';

	echo '<th>Type</th>';
	echo '<th>Waarde</th>';
	echo '<th data-hide="phone, tablet">Commentaar</th>';

	if ($app['s_admin'] || $s_owner)
	{
		echo '<th data-hide="phone, tablet">Zichtbaarheid</th>';
		echo '<th data-sort-ignore="true" ';
		echo 'data-hide="phone, tablet">Verwijderen</th>';
	}

	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach ($contacts as $c)
	{
		$out = [];

		$out[] = $c['abbrev'];

		if (($c['flag_public'] < $app['s_access_level']) && !$s_owner)
		{
			$out_c = '<span class="btn btn-default btn-xs">verborgen</span>';
			$out[] = $out_c;
			$out[] = $out_c;
		}
		else if ($s_owner || $app['s_admin'])
		{
			$out_c = $app['link']->link_no_attr('contacts', $app['pp_ary'],
				['edit' => $c['id'], 'uid' => $uid], $c['value']);

			if ($c['abbrev'] == 'adr')
			{
				$app['distance']->set_to_geo($c['value']);

				if (!$app['s_elas_guest'] && !$app['s_master'])
				{
					$out_c .= $app['distance']->set_from_geo('', $app['s_id'], $app['s_schema'])
						->calc()
						->format_parenthesis();
				}
			}

			$out[] = $out_c;

			if (isset($c['comments']))
			{
				$out[] = $app['link']->link_no_attr('contacts', $app['pp_ary'],
					['edit' => $c['id'], 'uid' => $uid], $c['comments']);
			}
			else
			{
				$out[] = '&nbsp;';
			}
		}
		else if ($c['abbrev'] === 'mail')
		{
			$out[] = '<a href="mailto:' . $c['value'] . '">' .
				$c['value'] . '</a>';

			$out[] = htmlspecialchars($c['comments'], ENT_QUOTES);
		}
		else if ($c['abbrev'] === 'web')
		{
			$out[] = '<a href="' . $c['value'] . '">' .
				$c['value'] .  '</a>';

			$out[] = htmlspecialchars($c['comments'], ENT_QUOTES);
		}
		else
		{
			$out_c = htmlspecialchars($c['value'], ENT_QUOTES);

			if ($c['abbrev'] == 'adr')
			{
				$app['distance']->set_to_geo($c['value']);

				if (!$app['s_elas_guest'] && !$app['s_master'])
				{
					$out_c .= $app['distance']->set_from_geo('', $app['s_id'], $app['s_schema'])
						->calc()
						->format_parenthesis();
				}
			}

			$out[] = $out_c;

			$out[] = htmlspecialchars($c['comments'], ENT_QUOTES);
		}

		if ($app['s_admin'] || $s_owner)
		{
			$out[] = $app['access_control']->get_label($c['flag_public']);

			$out[] = $app['link']->link_fa('contacts', $app['pp_ary'],
				['del' => $c['id'], 'uid' => $uid], 'Verwijderen',
				['class' => 'btn btn-danger btn-xs'], 'times');
		}

		echo '<tr><td>';
		echo implode('</td><td>', $out);
		echo '</td></tr>';
	}

	echo '</tbody>';

	echo '</table>';

	if ($app['distance']->has_to_data() && $app['p_inline'])
	{
		echo '<div class="panel-footer">';
		echo '<div class="user_map" id="map" data-markers="';
		echo $app['distance']->get_to_data();
		echo '" ';
		echo 'data-token="';
		echo $app['mapbox_token'];
		echo '"></div>';
		echo '</div>';
	}

	echo '</div></div>';

	echo '</div>';

	if ($app['p_inline'])
	{
		exit;
	}

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 *
 */

if (!$app['s_admin'])
{
	$app['alert']->error('Je hebt geen toegang tot deze pagina.');
	redirect_default_page();
}

$s_owner = !$app['s_guest']
	&& $app['s_system_self']
	&& $app['s_id'] == $uid
	&& $app['s_id'] && $uid;

$params = [
	'sort'	=> [
		'orderby'	=> $sort['orderby'] ?? 'c.id',
		'asc'		=> $sort['asc'] ?? 0,
	],
	'p'	=> [
		'start'		=> $pag['start'] ?? 0,
		'limit'		=> $pag['limit'] ?? 25,
	],
];

$params_sql = $where_sql = [];

if (isset($filter['uid']))
{
	$params['f']['uid'] = $filter['uid'];
	$filter['code'] = $app['account']->str($filter['uid'], $app['tschema']);
}

if (isset($filter['code']) && $filter['code'])
{
	[$code] = explode(' ', trim($filter['code']));

	$fuid = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.users
		where letscode = ?', [$code]);

	if ($fuid)
	{
		$where_sql[] = 'c.id_user = ?';
		$params_sql[] = $fuid;
		$params['f']['code'] = $app['account']->str($fuid, $app['tschema']);
	}
	else
	{
		$where_sql[] = '1 = 2';
	}
}

if (isset($filter['q']) && $filter['q'])
{
	$where_sql[] = '(c.value ilike ? or c.comments ilike ?)';
	$params_sql[] = '%' . $filter['q'] . '%';
	$params_sql[] = '%' . $filter['q'] . '%';
	$params['f']['q'] = $filter['q'];
}

if (isset($filter['abbrev']) && $filter['abbrev'])
{
	$where_sql[] = 'tc.abbrev = ?';
	$params_sql[] = $filter['abbrev'];
	$params['f']['abbrev'] = $filter['abbrev'];
}

if (isset($filter['access']) && $filter['access'] != 'all')
{
	switch ($filter['access'])
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
			$filter['access'] = 'all';
			break;
	}

	if ($filter['access'] !== 'all')
	{
		$where_sql[] = 'c.flag_public = ?';
		$params_sql[] = $acc;

	}

	$params['f']['access'] = $filter['access'];
}

if (isset($filter['ustatus']) && $filter['ustatus'])
{
	switch ($filter['ustatus'])
	{
		case 'new':
			$where_sql[] = 'u.adate > ? and u.status = 1';
			$params_sql[] = gmdate('Y-m-d H:i:s', $app['new_user_treshold']);
			break;
		case 'leaving':
			$where_sql[] = 'u.status = 2';
			break;
		case 'active':
			$where_sql[] = 'u.status in (1, 2)';
			break;
		case 'inactive':
			$where_sql[] = 'u.status = 0';
			break;
		case 'ip':
			$where_sql[] = 'u.status = 5';
			break;
		case 'im':
			$where_sql[] = 'u.status = 6';
			break;
		case 'extern':
			$where_sql[] = 'u.status = 7';
			break;
		default:
			$filter['ustatus'] = 'all';
			break;

		$params['f']['ustatus'] = $filter['ustatus'];
	}
}
else
{
	$params['f']['ustatus'] = 'all';
}

$user_table_sql = '';

if ($params['f']['ustatus'] !== 'all'
	|| $params['sort']['orderby'] === 'u.letscode')
{
	$user_table_sql = ', ' . $app['tschema'] . '.users u ';
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
	from ' . $app['tschema'] . '.contact c, ' .
		$app['tschema'] . '.type_contact tc' . $user_table_sql . '
	where c.id_type_contact = tc.id' . $where_sql;

$row_count = $app['db']->fetchColumn('select count(c.*)
	from ' . $app['tschema'] . '.contact c, ' .
		$app['tschema'] . '.type_contact tc' . $user_table_sql . '
	where c.id_type_contact = tc.id' . $where_sql, $params_sql);

$query .= ' order by ' . $params['sort']['orderby'] . ' ';
$query .= $params['sort']['asc'] ? 'asc ' : 'desc ';
$query .= ' limit ' . $params['p']['limit'];
$query .= ' offset ' . $params['p']['start'];

$contacts = $app['db']->fetchAll($query, $params_sql);

$app['pagination']->init('contacts', $app['pp_ary'],
	$row_count, $params, $app['p_inline']);

$asc_preset_ary = [
	'asc'		=> 0,
	'fa' 		=> 'sort',
];

$tableheader_ary = [
	'tc.abbrev' 	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Type']),
	'c.value'		=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Waarde']),
	'u.letscode'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Gebruiker']),
	'c.comments'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Commentaar',
		'data_hide'	=> 'phone,tablet']),
	'c.flag_public' => array_merge($asc_preset_ary, [
		'lbl' 		=> 'Zichtbaar',
		'data_hide'	=> 'phone, tablet']),
	'del' 			=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Verwijderen',
		'data_hide'	=> 'phone, tablet',
		'no_sort'	=> true]),
];

$tableheader_ary[$params['sort']['orderby']]['asc']
	= $params['sort']['asc'] ? 0 : 1;
$tableheader_ary[$params['sort']['orderby']]['fa']
	= $params['sort']['asc'] ? 'sort-asc' : 'sort-desc';

unset($tableheader_ary['c.id']);

$abbrev_ary = [];

$rs = $app['db']->prepare('select abbrev
	from ' . $app['tschema'] . '.type_contact');

$rs->execute();

while($row = $rs->fetch())
{
	$abbrev_ary[$row['abbrev']] = $row['abbrev'];
}

$app['btn_nav']->csv();

$app['btn_top']->add('contacts', $app['pp_ary'],
	['add' => 1], 'Contact toevoegen');

$filtered = !isset($filter['uid']) && (
	(isset($filter['q']) && $filter['q'] !== '')
	|| (isset($filter['abbrev']) && $filter['abbrev'] !== '')
	|| (isset($filter['code']) && $filter['code'] !== '')
	|| (isset($filter['ustatus'])
		&& !in_array($filter['ustatus'], ['all', '']))
	|| (isset($filter['access'])
		&& !in_array($filter['access'], ['all', ''])));

$panel_collapse = !$filtered;

$app['assets']->add(['typeahead', 'typeahead.js']);

$app['heading']->add('Contacten');
$app['heading']->add_filtered($filtered);
$app['heading']->btn_filter();
$app['heading']->fa('map-marker');

include __DIR__ . '/include/header.php';

echo '<div id="filter" class="panel panel-info';
echo $panel_collapse ? ' collapse' : '';
echo '">';

echo '<div class="panel-heading">';

echo '<form method="get" class="form-horizontal">';

echo '<div class="row">';

echo '<div class="col-sm-4">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="q" value="';
echo $filter['q'] ?? '';
echo '" name="f[q]" placeholder="Zoeken">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-4">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo 'Type';
echo '</span>';
echo '<select class="form-control" id="abbrev" name="f[abbrev]">';
echo $app['select']->get_options(array_merge(['' => ''], $abbrev_ary), $filter['abbrev'] ?? '');
echo '</select>';
echo '</div>';
echo '</div>';

$access_options = [
	'all'		=> '',
	'admin'		=> 'admin',
	'users'		=> 'leden',
	'interlets'	=> 'interSysteem',
];

if (!$app['intersystem_en'])
{
	unset($access_options['interlets']);
}

echo '<div class="col-sm-4">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo 'Zichtbaar';
echo '</span>';
echo '<select class="form-control" id="access" name="f[access]">';
echo $app['select']->get_options($access_options, $filter['access'] ?? 'all');
echo '</select>';
echo '</div>';
echo '</div>';

echo '</div>';

echo '<div class="row">';

$user_status_options = [
	'all'		=> 'Alle',
	'active'	=> 'Actief',
	'new'		=> 'Enkel instappers',
	'leaving'	=> 'Enkel uitstappers',
	'inactive'	=> 'Inactief',
	'ip'		=> 'Info-pakket',
	'im'		=> 'Info-moment',
	'extern'	=> 'Extern',
];

echo '<div class="col-sm-5">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo 'Status ';
echo '<i class="fa fa-user"></i>';
echo '</span>';
echo '<select class="form-control" ';
echo 'id="ustatus" name="f[ustatus]">';
echo $app['select']->get_options($user_status_options, $filter['ustatus'] ?? 'all');
echo '</select>';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-5">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon" id="code_addon">Van ';
echo '<span class="fa fa-user"></span></span>';

$typeahead_ary = [];

foreach (['active', 'inactive', 'ip', 'im', 'extern'] as $t_stat)
{
	$typeahead_ary[] = [
		'accounts', [
			'status'	=> $t_stat,
			'schema'	=> $app['tschema'],
		],
	];
}

echo '<input type="text" class="form-control" ';
echo 'aria-describedby="letscode_addon" ';
echo 'data-typeahead="';
echo $app['typeahead']->get($typeahead_ary);
echo '" ';
echo 'data-newuserdays="';
echo $app['config']->get('newuserdays', $app['tschema']);
echo '" ';
echo 'name="f[code]" id="code" placeholder="Account Code" ';
echo 'value="';
echo $filter['code'] ?? '';
echo '">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-2">';
echo '<input type="submit" value="Toon" ';
echo 'class="btn btn-default btn-block">';
echo '</div>';

echo '</div>';

$params_form = $params;
unset($params_form['f']);
unset($params_form['uid']);
unset($params_form['p']['start']);

$params_form['r'] = 'admin';
$params_form['u'] = $app['s_id'];

$params_form = http_build_query($params_form, 'prefix', '&');
$params_form = urldecode($params_form);
$params_form = explode('&', $params_form);

foreach ($params_form as $param)
{
	[$name, $value] = explode('=', $param);

	if (!isset($value) || $value === '')
	{
		continue;
	}

	echo '<input name="' . $name . '" ';
	echo 'value="' . $value . '" type="hidden">';
}

echo '</form>';

echo '</div>';
echo '</div>';

echo $app['pagination']->get();

if (!count($contacts))
{
	echo '<br>';
	echo '<div class="panel panel-default">';
	echo '<div class="panel-body">';
	echo '<p>Er zijn geen resultaten.</p>';
	echo '</div></div>';

	echo $app['pagination']->get();

	include __DIR__ . '/include/footer.php';
	exit;
}

echo '<div class="panel panel-danger">';
echo '<div class="table-responsive">';
echo '<table class="table table-hover ';
echo 'table-striped table-bordered footable csv" ';
echo 'data-sort="false">';

echo '<thead>';
echo '<tr>';

$th_params = $params;

foreach ($tableheader_ary as $key_orderby => $data)
{
	echo '<th';

	if (isset($data['data_hide']))
	{
		echo ' data-hide="' . $data['data_hide'] . '"';
	}

	echo '>';

	if (isset($data['no_sort']))
	{
		echo $data['lbl'];
	}
	else
	{
		$th_params['sort'] = [
			'orderby'	=> $key_orderby,
			'asc'		=> $data['asc'],
		];

		echo $app['link']->link_fa('contacts', $app['pp_ary'],
			$th_params, $data['lbl'], [], $data['fa']);
	}

	echo '</th>';
}

echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach ($contacts as $c)
{
-	$out = [];

	$out[] = $c['abbrev'];

	if (isset($c['value']))
	{
		$out[] = $app['link']->link_no_attr('contacts', $app['pp_ary'],
			['edit' => $c['id']], $c['value']);
	}
	else
	{
		$out[] = '&nbsp;';
	}

	$out[] = $app['account']->link($c['id_user'], $app['pp_ary']);

	if (isset($c['comments']))
	{
		$out[] = $app['link']->link_no_attr('contacts', $app['pp_ary'],
			['edit' => $c['id']], $c['comments']);
	}
	else
	{
		$out[] = '&nbsp;';
	}

	$out[] = $app['access_control']->get_label($c['flag_public']);

	$out[] = $app['link']->link_fa('contacts', $app['pp_ary'],
		['del' => $c['id']], 'Verwijderen',
		['class' => 'btn btn-danger btn-xs'],
		'times');

	echo '<tr><td>';
	echo implode('</td><td>', $out);
	echo '</td></tr>';
}

echo '</tbody>';

echo '</table>';

echo '</div></div>';

echo $app['pagination']->get();

include __DIR__ . '/include/footer.php';
