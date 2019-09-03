<?php declare(strict_types=1);

if ($app['s_anonymous'])
{
	exit;
}

use cnst\role as cnst_role;
use cnst\status as cnst_status;

$q = $_GET['q'] ?? '';
$status = $_GET['status'] ?? false;
$id = $_GET['id'] ?? false;
$tdays = $_GET['tdays'] ?? 365;
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = $_GET['add'] ?? false;
$link = $_GET['link'] ?? false;
$pw = $_GET['pw'] ?? false;
$img = isset($_GET['img']) ? true : false;
$img_del = isset($_GET['img_del']) ? true : false;
$intersystem_code = $_GET['intersystem_code'] ?? false;
$password = $_POST['password'] ?? false;
$user_mail_submit = isset($_POST['user_mail_submit']) ? true : false;
$bulk_mail_submit = isset($_POST['bulk_mail_submit']) ? true : false;
$bulk_mail_test = isset($_POST['bulk_mail_test']) ? true : false;
$bulk_field = $_POST['bulk_field'] ?? false;
$selected_users = isset($_POST['sel']) && $_POST['sel'] != '' ? explode(',', $_POST['sel']) : [];

if ($add || $del || $bulk_mail_submit || $bulk_mail_test)
{
	if (!$app['pp_admin'])
	{
		exit;
	}
}
else if ($edit || $pw || $img_del || $password || $img)
{
	if (!($app['pp_admin'] || $app['s_user']))
	{
		exit;
	}
}

/**
 * selectors for bulk actions
 */
$bulk_field_submit = $bulk_submit = false;

if ($app['pp_admin'])
{
	$edit_fields_tabs = [
		'fullname_access'	=> [
			'lbl'				=> 'Zichtbaarheid Volledige Naam',
			'item_access'	=> true,
		],
		'adr_access'		=> [
			'lbl'		=> 'Zichtbaarheid adres',
			'item_access'	=> true,
		],
		'mail_access'		=> [
			'lbl'		=> 'Zichtbaarheid E-mail adres',
			'item_access'	=> true,
		],
		'tel_access'		=> [
			'lbl'		=> 'Zichtbaarheid telefoonnummer',
			'item_access'	=> true,
		],
		'gsm_access'		=> [
			'lbl'		=> 'Zichtbaarheid GSM-nummer',
			'item_access'	=> true,
		],
		'comments'			=> [
			'lbl'		=> 'Commentaar',
			'type'		=> 'text',
			'string'	=> true,
			'fa'		=> 'comment-o',
		],
		'accountrole'		=> [
			'lbl'		=> 'Rechten',
			'options'	=> cnst_role::LABEL_ARY,
			'string'	=> true,
			'fa'		=> 'hand-paper-o',
		],
		'status'			=> [
			'lbl'		=> 'Status',
			'options'	=> cnst_status::LABEL_ARY,
			'fa'		=> 'star-o',
		],
		'admincomment'		=> [
			'lbl'		=> 'Commentaar van de Admin',
			'type'		=> 'text',
			'string'	=> true,
			'fa'		=> 'comment-o',
		],
		'minlimit'			=> [
			'lbl'		=> 'Minimum Account Limiet',
			'type'		=> 'number',
			'fa'		=> 'arrow-down',
		],
		'maxlimit'			=> [
			'lbl'		=> 'Maximum Account Limiet',
			'type'		=> 'number',
			'fa'		=> 'arrow-up',
		],
		'cron_saldo'		=> [
			'lbl'	=> 'Periodieke Overzichts E-mail (aan/uit)',
			'type'	=> 'checkbox',
		],
	];

	if ($app['request']->isMethod('POST') && $bulk_field)
	{
		if (isset($_POST[$bulk_field . '_bulk_submit']))
		{
			$bulk_field_submit = true;
		}
	}

	$bulk_submit = $bulk_field_submit || $bulk_mail_submit || $bulk_mail_test;
}

/**
 * mail to user
 */

if ($user_mail_submit && $id && $app['request']->isMethod('POST'))
{
	$user_mail_content = $_POST['user_mail_content'] ?? '';
	$user_mail_cc = isset($_POST['user_mail_cc']);

	$to_user = $app['user_cache']->get($id, $app['tschema']);

	if (!$app['pp_admin'] && !in_array($to_user['status'], [1, 2]))
	{
		$app['alert']->error('Je hebt geen rechten
			om een E-mail bericht naar een niet-actieve
			gebruiker te sturen');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
	}

	if ($app['s_master'])
	{
		$app['alert']->error('Het master account kan
			geen E-mail berichten versturen.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
	}

	if (!$app['s_schema'])
	{
		$app['alert']->error('Je hebt onvoldoende
			rechten om een E-mail bericht te versturen.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
	}

	if (!$user_mail_content)
	{
		$app['alert']->error('Fout: leeg bericht. E-mail niet verzonden.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
	}

	$reply_ary = $app['mail_addr_user']->get($app['s_id'], $app['s_schema']);

	if (!count($reply_ary))
	{
		$app['alert']->error('Fout: Je kan geen berichten naar andere gebruikers
			verzenden als er geen E-mail adres is ingesteld voor je eigen account.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
	}

	$from_contacts = $app['db']->fetchAll('select c.value, tc.abbrev
		from ' . $app['s_schema'] . '.contact c, ' .
			$app['s_schema'] . '.type_contact tc
		where c.flag_public >= ?
			and c.id_user = ?
			and c.id_type_contact = tc.id',
			[cnst::ACCESS_ARY[$to_user['accountrole']], $app['s_id']]);

	$from_user = $app['user_cache']->get($app['s_id'], $app['s_schema']);

	$vars = [
		'from_contacts'		=> $from_contacts,
		'from_user'			=> $from_user,
		'from_schema'		=> $app['s_schema'],
		'to_user'			=> $to_user,
		'to_schema'			=> $app['tschema'],
		'is_same_system'	=> $app['s_system_self'],
		'msg_content'		=> $user_mail_content,
	];

	$mail_template = $app['s_system_self']
		? 'user_msg/msg'
		: 'user_msg/msg_intersystem';

	$app['queue.mail']->queue([
		'schema'	=> $app['tschema'],
		'to'		=> $app['mail_addr_user']->get($id, $app['tschema']),
		'reply_to'	=> $reply_ary,
		'template'	=> $mail_template,
		'vars'		=> $vars,
	], 8000);

	if ($user_mail_cc)
	{
		$mail_template = $app['s_system_self']
			? 'user_msg/copy'
			: 'user_msg/copy_intersystem';

		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'to' 		=> $app['mail_addr_user']->get($app['s_id'], $app['s_schema']),
			'template' 	=> $mail_template,
			'vars'		=> $vars,
		], 8000);
	}

	$app['alert']->success('E-mail bericht verzonden.');

	$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
}

/*
 * upload image
 */

if ($app['request']->isMethod('POST') && $img && $id )
{
	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $app['s_id'] == $id
		&& $id;

	if (!($s_owner || $app['pp_admin']))
	{
		echo json_encode(['error' => 'Je hebt onvoldoende rechten voor deze actie.']);
		exit;
	}

	$user = $app['user_cache']->get($id, $app['tschema']);

	$image = $_FILES['image'] ?: null;

	if (!$image)
	{
		echo json_encode(['error' => 'Afbeeldingsbestand ontbreekt.']);
		exit;
	}

	$size = $image['size'];
	$tmp_name = $image['tmp_name'];
	$type = $image['type'];

	if ($size > 400 * 1024)
	{
		echo json_encode(['error' => 'Het bestand is te groot.']);
		exit;
	}

	if ($type != 'image/jpeg')
	{
		echo json_encode(['error' => 'Ongeldig bestandstype.']);
		exit;
	}

	//

	$exif = exif_read_data($tmp_name);

	$orientation = $exif['COMPUTED']['Orientation'] ?? false;

	$tmpfile = tempnam(sys_get_temp_dir(), 'img');

	$imagine = new Imagine\Imagick\Imagine();

	$image = $imagine->open($tmp_name);

	switch ($orientation)
	{
		case 3:
		case 4:
			$image->rotate(180);
			break;
		case 5:
		case 6:
			$image->rotate(-90);
			break;
		case 7:
		case 8:
			$image->rotate(90);
			break;
		default:
			break;
	}

	$image->thumbnail(new Imagine\Image\Box(400, 400), Imagine\Image\ImageInterface::THUMBNAIL_INSET);
	$image->save($tmpfile);

	//

	$filename = $app['tschema'] . '_u_' . $id . '_';
	$filename .= sha1($filename . microtime()) . '.jpg';

	$err = $app['s3']->img_upload($filename, $tmpfile);

	if ($err)
	{
		$app['monolog']->error('pict: ' .  $err . ' -- ' .
			$filename, ['schema' => $app['tschema']]);

		$response = ['error' => 'Afbeelding opladen mislukt.'];
	}
	else
	{
		$app['db']->update($app['tschema'] . '.users', [
			'"PictureFile"'	=> $filename
		],['id' => $id]);

		$app['monolog']->info('User image ' . $filename .
			' uploaded. User: ' . $id,
			['schema' => $app['tschema']]);

		$app['user_cache']->clear($id, $app['tschema']);

		$response = ['success' => 1, 'filename' => $filename];
	}

	unlink($tmp_name);

	header('Pragma: no-cache');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Content-Disposition: inline; filename="files.json"');
	header('X-Content-Type-Options: nosniff');
	header('Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size');
	header('Vary: Accept');

	echo json_encode($response);
	exit;
}

/**
 * delete image
 */

if ($img_del && $id)
{
	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $app['s_id'] == $id
		&& $id;

	if (!($s_owner || $app['pp_admin']))
	{
		$app['alert']->error('Je hebt onvoldoende rechten om de foto te verwijderen.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
	}

	$user = $app['user_cache']->get($id, $app['tschema']);

	if (!$user)
	{
		$app['alert']->error('De gebruiker bestaat niet.');

		$app['link']->redirect('users', $app['pp_ary'], []);
	}

	$file = $user['PictureFile'];

	if ($file == '' || !$file)
	{
		$app['alert']->error('De gebruiker heeft geen foto.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
	}

	if ($app['request']->isMethod('POST'))
	{
		$app['db']->update($app['tschema'] . '.users',
			['"PictureFile"' => ''],
			['id' => $id]);
		$app['user_cache']->clear($id, $app['tschema']);
		$app['alert']->success('Profielfoto verwijderd.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
	}

	$app['heading']->add('Profielfoto ');

	if ($app['pp_admin'])
	{
		$app['heading']->add('van ');
		$app['heading']->add($app['account']->link($id, $app['pp_ary']));
		$app['heading']->add(' ');
	}

	$app['heading']->add('verwijderen?');

	include __DIR__ . '/include/header.php';

	echo '<div class="row">';
	echo '<div class="col-xs-6">';
	echo '<div class="thumbnail">';
	echo '<img src="';
	echo $app['s3_url'] . $file;
	echo '" class="img-rounded">';
	echo '</div>';
	echo '</div>';

	echo '</div>';

	echo '<form method="post">';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo $app['link']->btn_cancel('users', $app['pp_ary'], ['id' => $id]);

	echo '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';

	exit;
}

/**
 * bulk actions
 */

if ($bulk_submit && $app['request']->isMethod('POST')
	&& $app['pp_admin'])
{
	$verify = ($bulk_mail_submit || $bulk_mail_test) ? 'verify_mail' : 'verify_' . $bulk_field;
	$verify = isset($_POST[$verify]) ? true : false;

	if (!$verify)
	{
		$errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
	}

	if ($bulk_field_submit)
	{
		$value = $_POST[$bulk_field] ?? '';
	}

	if ($bulk_mail_test || $bulk_mail_submit)
	{
		$bulk_mail_subject = $_POST['bulk_mail_subject'];
		$bulk_mail_content = $_POST['bulk_mail_content'];
		$bulk_mail_cc = isset($_POST['bulk_mail_cc']);

		if (!$bulk_mail_subject)
		{
			$errors[] = 'Gelieve een onderwerp in te vullen voor je E-mail.';
		}

		if (!$bulk_mail_content)
		{
			$errors[] = 'Het E-mail bericht is leeg.';
		}

		if (!$app['config']->get('mailenabled', $app['tschema']))
		{
			$errors[] = 'De E-mail functies zijn niet ingeschakeld. Zie instellingen.';
		}

		if ($app['s_master'])
		{
			$errors[] = 'Het master account kan geen E-mail berichten verzenden.';
		}
	}

	if (!count($selected_users) && !$bulk_mail_test)
	{
		$errors[] = 'Selecteer ten minste één gebruiker voor deze actie.';
	}

	if ($error_token = $app['form_token']->get_error())
	{
		$errors[] = $error_token;
	}

	if ($bulk_field && strpos($bulk_field, '_access') !== false)
	{
		$access_value = $app['request']->request->get('access', '');

		if (!$access_value)
		{
			$errors[] = 'Vul een zichtbaarheid in.';
		}
	}

	if (count($errors))
	{
		$app['alert']->error($errors);
	}
	else
	{
		$user_ids = $selected_users;
	}

	$selected_users = array_combine($selected_users, $selected_users);
}

/**
 * bulk action: change a field for multiple users
 */

if ($app['pp_admin'] && !count($errors)
	&& $bulk_field_submit
	&& $app['request']->isMethod('POST'))
{
	$users_log = '';

	$rows = $app['db']->executeQuery('select letscode, name, id
		from ' . $app['tschema'] . '.users
		where id in (?)',
		[$user_ids], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($rows as $row)
	{
		$users_log .= ', ';
		$users_log .= $app['account']->str_id($row['id'], $app['tschema'], false, true);
	}

	$users_log = ltrim($users_log, ', ');

	if ($bulk_field == 'fullname_access')
	{
		$fullname_access_role = $app['item_access']->get_xdb($access_value);

		foreach ($user_ids as $user_id)
		{
			$app['xdb']->set('user_fullname_access', $user_id, [
				'fullname_access' => $fullname_access_role,
			], $app['tschema']);
			$app['predis']->del($app['tschema'] . '_user_' . $user_id);
		}

		$app['monolog']->info('bulk: Set fullname_access to ' .
			$access_value . ' for users ' .
			$users_log, ['schema' => $app['tschema']]);

		$app['alert']->success('De zichtbaarheid van de
			volledige naam werd aangepast.');

		$app['link']->redirect('users', $app['pp_ary'], []);
	}
	else if (['accountrole' => 1, 'status' => 1, 'comments' => 1,
		'admincomment' => 1, 'minlimit' => 1, 'maxlimit' => 1][$bulk_field])
	{
		if ($bulk_field == 'minlimit')
		{
			$value = $value == '' ? -999999999 : $value;
		}

		if ($bulk_field == 'maxlimit')
		{
			$value = $value == '' ? 999999999 : $value;
		}

		$type = $edit_fields_tabs[$bulk_field]['string'] ? \PDO::PARAM_STR : \PDO::PARAM_INT;

		$app['db']->executeUpdate('update ' . $app['tschema'] . '.users
			set ' . $bulk_field . ' = ? where id in (?)',
			[$value, $user_ids],
			[$type, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

		foreach ($user_ids as $user_id)
		{
			$app['predis']->del($app['tschema'] . '_user_' . $user_id);
		}

		if ($bulk_field == 'status')
		{
			delete_thumbprint('active');
			delete_thumbprint('extern');
		}

		$app['monolog']->info('bulk: Set ' . $bulk_field .
			' to ' . $value .
			' for users ' . $users_log,
			['schema' => $app['tschema']]);

		$app['intersystems']->clear_cache($app['s_schema']);

		$app['alert']->success('Het veld werd aangepast.');

		$app['link']->redirect('users', $app['pp_ary'], []);
	}
	else if (['adr_access' => 1, 'mail_access' => 1, 'tel_access' => 1, 'gsm_access' => 1][$bulk_field])
	{
		[$abbrev] = explode('_', $bulk_field);

		$id_type_contact = $app['db']->fetchColumn('select id
			from ' . $app['tschema'] . '.type_contact
			where abbrev = ?', [$abbrev]);

		$flag_public = $app['item_access']->get_flag_public($acces_value);

		$app['db']->executeUpdate('update ' . $app['tschema'] . '.contact
		set flag_public = ?
		where id_user in (?) and id_type_contact = ?',
			[$flag_public, $user_ids, $id_type_contact],
			[\PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY, \PDO::PARAM_INT]);

		$app['monolog']->info('bulk: Set ' . $bulk_field .
			' to ' . $access_value .
			' for users ' . $users_log,
			['schema' => $app['tschema']]);
		$app['alert']->success('Het veld werd aangepast.');

		$app['link']->redirect('users', $app['pp_ary'], []);
	}
	else if ($bulk_field == 'cron_saldo')
	{
		$value = $value ? true : false;

		$app['db']->executeUpdate('update ' . $app['tschema'] . '.users
			set cron_saldo = ?
			where id in (?)',
			[$value, $user_ids],
			[\PDO::PARAM_BOOL, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

		foreach ($user_ids as $user_id)
		{
			$app['predis']->del($app['tschema'] . '_user_' . $user_id);
		}

		$value = $value ? 'on' : 'off';

		$app['monolog']->info('bulk: Set periodic mail to ' .
			$value . ' for users ' .
			$users_log,
			['schema' => $app['tschema']]);

		$app['intersystems']->clear_cache($app['s_schema']);

		$app['alert']->success('Het veld werd aangepast.');

		$app['link']->redirect('users', $app['pp_ary'], []);
	}
}

/**
 * bulk action: mail
 */

if ($app['pp_admin'])
{
	$map_template_vars = [
		'naam' 					=> 'name',
		'volledige_naam'		=> 'fullname',
		'saldo'					=> 'saldo',
		'account_code'			=> 'letscode',
	];
}

if ($app['pp_admin']
	&& !count($errors)
	&& ($bulk_mail_submit || $bulk_mail_test)
	&& $app['request']->isMethod('POST'))
{
	if ($bulk_mail_test)
	{
		$sel_ary = [$app['s_id'] => true];
		$user_ids = [$app['s_id']];
	}
	else
	{
		$sel_ary = $selected_users;
	}

	$alert_users_sent_ary = $mail_users_sent_ary = [];

	$config_htmlpurifier = HTMLPurifier_Config::createDefault();
	$config_htmlpurifier->set('Cache.DefinitionImpl', null);
	$htmlpurifier = new HTMLPurifier($config_htmlpurifier);
	$bulk_mail_content = $htmlpurifier->purify($bulk_mail_content);

	$sel_users = $app['db']->executeQuery('select u.*, c.value as mail
		from ' . $app['tschema'] . '.users u, ' .
			$app['tschema'] . '.contact c, ' .
			$app['tschema'] . '.type_contact tc
		where u.id in (?)
			and u.id = c.id_user
			and c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'',
			[$user_ids], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($sel_users as $sel_user)
	{
		if (!isset($sel_ary[$sel_user['id']]))
		{
			// avoid duplicate send when multiple mail addresses for one user.
			continue;
		}

		unset($sel_ary[$sel_user['id']]);

		$vars = [
			'subject'	=> $bulk_mail_subject,
		];

		foreach ($map_template_vars as $key => $val)
		{
			$vars[$key] = $sel_user[$val];
		}

		$app['queue.mail']->queue([
			'schema'			=> $app['tschema'],
			'to' 				=> $app['mail_addr_user']->get($sel_user['id'], $app['tschema']),
			'pre_html_template' => $bulk_mail_content,
			'reply_to' 			=> $app['mail_addr_user']->get($app['s_id'], $app['tschema']),
			'vars'				=> $vars,
			'template'			=> 'skeleton',
		], random_int(1000, 4000));

		$alert_users_sent_ary[] = $app['account']->link($sel_user['id'], $app['pp_ary']);
		$mail_users_sent_ary[] = $app['account']->link_url($sel_user['id'], $app['pp_ary']);
	}

	if (count($alert_users_sent_ary))
	{
		$msg_users_sent = 'E-mail verzonden naar ';
		$msg_users_sent .= count($alert_users_sent_ary);
		$msg_users_sent .= ' ';
		$msg_users_sent .= count($alert_users_sent_ary) > 1 ? 'accounts' : 'account';
		$msg_users_sent .= ':';
		$alert_users_sent = $msg_users_sent . '<br>';
		$alert_users_sent .= implode('<br>', $alert_users_sent_ary);

		$app['alert']->success($alert_users_sent);
	}
	else
	{
		$app['alert']->warning('Geen E-mails verzonden.');
	}

	if (count($sel_ary))
	{
		$msg_missing_users = 'Naar volgende gebruikers werd geen
			E-mail verzonden wegens ontbreken van E-mail adres:';

		$alert_missing_users = $msg_missing_users . '<br>';
		$mail_missing_users = $msg_missing_users . '<br />';

		foreach ($sel_ary as $warning_user_id => $dummy)
		{
			$alert_missing_users .= $app['account']->link($warning_user_id, $app['pp_ary']);
			$alert_missing_users .= '<br>';

			$mail_missing_users .= $app['account']->link_url($warning_user_id, $app['pp_ary']);
			$mail_missing_users .= '<br />';
		}

		$app['alert']->warning($alert_missing_users);
	}

	if ($bulk_mail_submit && $count && $bulk_mail_cc)
	{
		$vars = [
			'subject'	=> 'Kopie: ' . $bulk_mail_subject,
		];

		foreach ($map_template_vars as $key => $trans)
		{
			$vars[$key] = '{{ ' . $key . ' }}';
		}

		$mail_users_info = $msg_users_sent . '<br />';
		$mail_users_info .= implode('<br />', $alert_users_sent_ary);
		$mail_users_info .= '<br /><br />';

		if (isset($mail_missing_users))
		{
			$mail_users_info .= $mail_missing_users;
			$mail_users_info .= '<br/>';
		}

		$mail_users_info .= '<hr /><br />';

		$app['queue.mail']->queue([
			'schema'			=> $app['tschema'],
			'to' 				=> $app['mail_addr_user']->get($app['s_id'], $app['tschema']),
			'template'			=> 'skeleton',
			'pre_html_template'	=> $mail_users_info . $bulk_mail_content,
			'vars'				=> $vars,
		], 8000);

		$app['monolog']->debug('#bulk mail:: ' .
			$mail_users_info . $bulk_mail_content,
			['schema' => $app['tschema']]);

		$app['link']->redirect('users', $app['pp_ary'], []);
	}
}

/**
 * Change password.
 */

if ($pw)
{
	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $pw == $app['s_id']
		&& $pw;

	if (!$app['pp_admin'] && !$s_owner)
	{
		$app['alert']->error('Je hebt onvoldoende rechten om het
			paswoord aan te passen voor deze gebruiker.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $pw]);
	}

	if($app['request']->isMethod('POST'))
	{
		$password = trim($_POST['password']);

		if (empty($password) || ($password == ''))
		{
			$errors[] = 'Vul paswoord in!';
		}

		if (!$app['pp_admin'] && $app['password_strength']->get($password) < 50)
		{
			$errors[] = 'Te zwak paswoord.';
		}

		if ($error_token = $app['form_token']->get_error())
		{
			$errors[] = $error_token;
		}

		if (empty($errors))
		{
			$update = [
				'password'	=> hash('sha512', $password),
				'mdate'		=> gmdate('Y-m-d H:i:s'),
			];

			if ($app['db']->update($app['tschema'] . '.users',
				$update,
				['id' => $pw]))
			{
				$app['user_cache']->clear($pw, $app['tschema']);
				$user = $app['user_cache']->get($pw, $app['tschema']);
				$app['alert']->success('Paswoord opgeslagen.');

				if (($user['status'] == 1 || $user['status'] == 2) && $_POST['notify'])
				{
					$to = $app['db']->fetchColumn('select c.value
						from ' . $app['tschema'] . '.contact c, ' .
							$app['tschema'] . '.type_contact tc
						where tc.id = c.id_type_contact
							and tc.abbrev = \'mail\'
							and c.id_user = ?', [$pw]);

					if ($to)
					{
						$vars = [
							'user_id'		=> $pw,
							'password'		=> $password,
						];

						$app['queue.mail']->queue([
							'schema'	=> $app['tschema'],
							'to' 		=> $app['mail_addr_user']->get($pw, $app['tschema']),
							'reply_to'	=> $app['mail_addr_system']->get_support($app['tschema']),
							'template'	=> 'password_reset/user',
							'vars'		=> $vars,
						], 8000);

						$app['alert']->success('Notificatie mail verzonden');
					}
					else
					{
						$app['alert']->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het paswoord op een andere manier door!');
					}
				}

				$app['link']->redirect('users', $app['pp_ary'], ['id' => $pw]);
			}
			else
			{
				$app['alert']->error('Paswoord niet opgeslagen.');
			}
		}
		else
		{
			$app['alert']->error($errors);
		}

	}

	$user = $app['user_cache']->get($pw, $app['tschema']);

	$app['assets']->add([
		'generate_password.js',
	]);

	$app['heading']->add('Paswoord aanpassen');

	if (!$s_owner)
	{
		$app['heading']->add(' voor ');
		$app['heading']->add($app['account']->link($pw, $app['pp_ary']));
	}

	$app['heading']->fa('key');

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="password" class="control-label">';
	echo 'Paswoord</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-key"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="password" name="password" ';
	echo 'value="';
	echo $password;
	echo '" required>';
	echo '<span class="input-group-btn">';
	echo '<button class="btn btn-default" type="button" ';
	echo 'id="generate">Genereer</button>';
	echo '</span>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="notify" class="control-label">';
	echo '<input type="checkbox" name="notify" id="notify"';
	echo $user['status'] == 1 || $user['status'] == 2 ? ' checked="checked"' : ' readonly';
	echo '>';
	echo ' Verzend notificatie E-mail met nieuw paswoord. ';
	echo 'Dit is enkel mogelijk wanneer de Status ';
	echo 'actief is en E-mail adres ingesteld.';
	echo '</label>';
	echo '</div>';

	echo $app['link']->btn_cancel('users', $app['pp_ary'], ['id' => $pw]);

	echo '&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" ';
	echo 'class="btn btn-primary">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * delete a user.
 */

if ($del)
{
	if (!$app['pp_admin'])
	{
		$app['alert']->error('Je hebt onvoldoende rechten
			om een gebruiker te verwijderen.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $del]);
	}

	if ($app['s_id'] == $del)
	{
		$app['alert']->error('Je kan jezelf niet verwijderen.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $del]);
	}

	if ($app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.transactions
		where id_to = ? or id_from = ?', [$del, $del]))
	{
		$app['alert']->error('Een gebruiker met transacties
			kan niet worden verwijderd.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $del]);
	}

	$user = $app['user_cache']->get($del, $app['tschema']);

	if (!$user)
	{
		$app['alert']->error('De gebruiker bestaat niet.');

		$app['link']->redirect('users', $app['pp_ary'], []);
	}

	if ($app['request']->isMethod('POST'))
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			$app['link']->redirect('users', $app['pp_ary'], ['id' => $del]);
		}

		$verify = isset($_POST['verify']) ? true : false;

		if (!$verify)
		{
			$app['alert']->error('Het controle nazichts-vakje
				is niet aangevinkt.');

			$app['link']->redirect('users', $app['pp_ary'], ['id' => $del]);
		}

		$usr = $user['letscode'] . ' ' . $user['name'] . ' [id:' . $del . ']';
		$msgs = '';
		$st = $app['db']->prepare('select id, content,
				id_category, msg_type
			from ' . $app['tschema'] . '.messages
			where id_user = ?');

		$st->bindValue(1, $del);
		$st->execute();

		while ($row = $st->fetch())
		{
			$msgs .= $row['id'] . ': ' . $row['content'] . ', ';
		}
		$msgs = trim($msgs, '\n\r\t ,;:');

		if ($msgs)
		{
			$app['monolog']->info('Delete user ' . $usr .
				', deleted Messages ' . $msgs,
				['schema' => $app['tschema']]);

			$app['db']->delete($app['tschema'] . '.messages',
				['id_user' => $del]);
		}

		// remove orphaned images.

		$rs = $app['db']->prepare('select mp.id, mp."PictureFile"
			from ' . $app['tschema'] . '.msgpictures mp
				left join ' . $app['tschema'] . '.messages m on mp.msgid = m.id
			where m.id is null');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$app['db']->delete($app['tschema'] . '.msgpictures', ['id' => $row['id']]);
		}

		// update counts for each category

		$offer_count = $want_count = [];

		$rs = $app['db']->prepare('select m.id_category, count(m.*)
			from ' . $app['tschema'] . '.messages m, ' .
				$app['tschema'] . '.users u
			where  m.id_user = u.id
				and u.status IN (1, 2, 3)
				and msg_type = 1
			group by m.id_category');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$offer_count[$row['id_category']] = $row['count'];
		}

		$rs = $app['db']->prepare('select m.id_category, count(m.*)
			from ' . $app['tschema'] . '.messages m, ' .
				$app['tschema'] . '.users u
			where m.id_user = u.id
				and u.status IN (1, 2, 3)
				and msg_type = 0
			group by m.id_category');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$want_count[$row['id_category']] = $row['count'];
		}

		$all_cat = $app['db']->fetchAll('select id,
				stat_msgs_offers, stat_msgs_wanted
			from ' . $app['tschema'] . '.categories
			where id_parent is not null');

		foreach ($all_cat as $val)
		{
			$offers = $val['stat_msgs_offers'];
			$wants = $val['stat_msgs_wanted'];
			$cat_id = $val['id'];

			$want_count[$cat_id] = $want_count[$cat_id] ?? 0;
			$offer_count[$cat_id] = $offer_count[$cat_id] ?? 0;

			if ($want_count[$cat_id] == $wants && $offer_count[$cat_id] == $offers)
			{
				continue;
			}

			$stats = [
				'stat_msgs_offers'	=> $offer_count[$cat_id] ?? 0,
				'stat_msgs_wanted'	=> $want_count[$cat_id] ?? 0,
			];

			$app['db']->update($app['tschema'] . '.categories',
				$stats,
				['id' => $cat_id]);
		}

		//delete contacts
		$app['db']->delete($app['tschema'] . '.contact',
			['id_user' => $del]);

		//delete fullname access record.
		$app['xdb']->del('user_fullname_access', $del, $app['tschema']);

		//finally, the user
		$app['db']->delete($app['tschema'] . '.users',
			['id' => $del]);
		$app['predis']->expire($app['tschema'] . '_user_' . $del, 0);

		$app['alert']->success('De gebruiker is verwijderd.');

		switch($user['status'])
		{
			case 0:
				delete_thumbprint('inactive');
				break;
			case 1:
			case 2:
				delete_thumbprint('active');
				break;
			case 5:
				delete_thumbprint('im');
				break;
			case 6:
				delete_thumbprint('ip');
				break;
			case 7:
				delete_thumbprint('extern');
				break;
			default:
				break;
		}

		$app['intersystems']->clear_cache($app['s_schema']);

		$app['link']->redirect('users', $app['pp_ary'], []);
	}

	$app['heading']->add('Gebruiker ');
	$app['heading']->add($app['account']->link($del, $app['pp_ary']));
	$app['heading']->add(' verwijderen?');
	$app['heading']->fa('user');

	include __DIR__ . '/include/header.php';

	echo '<p><font color="red">Alle Gegevens, Vraag en aanbod, ';
	echo 'Contacten en Afbeeldingen van ';
	echo $app['account']->link($del, $app['pp_ary']);
	echo ' worden verwijderd.</font></p>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post"">';

	echo '<div class="form-group">';
	echo '<label for="id_verify">';
	echo '<input type="checkbox" name="verify"';
	echo ' value="1" id="id_verify"> ';
	echo ' Ik ben wis en waarachtig zeker dat ';
	echo 'ik deze gebruiker wil verwijderen.';
	echo '</label>';
	echo '</div>';

	echo $app['link']->btn_cancel('users', $app['pp_ary'], ['id' => $del]);

	echo '&nbsp;';
	echo '<input type="submit" value="Verwijderen" ';
	echo 'name="zend" class="btn btn-danger">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * Edit or add a user
 */

if ($add || $edit)
{
	if ($add && !$app['pp_admin'])
	{
		$app['alert']->error('Je hebt geen rechten om
			een gebruiker toe te voegen.');

		$app['link']->redirect('users', $app['pp_ary'], []);
	}

	$s_owner =  !$app['s_guest']
		&& $app['s_system_self']
		&& $edit
		&& $app['s_id']
		&& $edit == $app['s_id'];

	if ($edit && !$app['pp_admin'] && !$s_owner)
	{
		$app['alert']->error('Je hebt geen rechten om
			deze gebruiker aan te passen.');

		$app['link']->redirect('users', $app['pp_ary'], ['id' => $edit]);
	}

	if ($app['pp_admin'])
	{
		$username_edit = $fullname_edit = true;
	}
	else if ($s_owner)
	{
		$username_edit = $app['config']->get('users_can_edit_username', $app['tschema']);
		$fullname_edit = $app['config']->get('users_can_edit_fullname', $app['tschema']);
	}
	else
	{
		$username_edit = $fullname_edit = false;
	}

	if ($app['request']->isMethod('POST'))
	{
		$user = [
			'postcode'		=> trim($_POST['postcode']),
			'birthday'		=> trim($_POST['birthday']) ?: null,
			'hobbies'		=> trim($_POST['hobbies']),
			'comments'		=> trim($_POST['comments']),
			'cron_saldo'	=> isset($_POST['cron_saldo']) ? 1 : 0,
			'lang'			=> 'nl'
		];

		if ($app['pp_admin'])
		{
			// hack eLAS compatibility (in eLAND limits can be null)
			$minlimit = trim($_POST['minlimit']);
			$maxlimit = trim($_POST['maxlimit']);

			$minlimit = $minlimit === '' ? -999999999 : $minlimit;
			$maxlimit = $maxlimit === '' ? 999999999 : $maxlimit;

			$user += [
				'letscode'		=> trim($_POST['letscode']),
				'accountrole'	=> $_POST['accountrole'],
				'status'		=> $_POST['status'],
				'admincomment'	=> trim($_POST['admincomment']),
				'minlimit'		=> $minlimit,
				'maxlimit'		=> $maxlimit,
				'presharedkey'	=> trim($_POST['presharedkey']),
			];

			$contact = $_POST['contact'];
			$notify = $_POST['notify'];
			$password = trim($_POST['password']);

			$mail_unique_check_sql = 'select count(c.value)
					from ' . $app['tschema'] . '.contact c, ' .
						$app['tschema'] . '.type_contact tc, ' .
						$app['tschema'] . '.users u
					where c.id_type_contact = tc.id
						and tc.abbrev = \'mail\'
						and c.value = ?
						and c.id_user = u.id
						and u.status in (1, 2)';

			if ($edit)
			{
				$mail_unique_check_sql .= ' and u.id <> ?';
			}

			$mailadr = false;

			$st = $app['db']->prepare($mail_unique_check_sql);

			foreach ($contact as $key => $c)
			{
				$access_contact = $app['request']->request->get('contact_access_' . $key);

				if ($c['value'] && !$access_contact)
				{
					$errors[] = 'Vul een zichtbaarheid in.';
				}

				$contact[$key]['flag_public'] = $app['item_access']->get_flag_public($access_contact);
			}

			foreach ($contact as $key => $c)
			{
				if ($c['abbrev'] == 'mail')
				{
					$mailadr = trim($c['value']);

					if ($mailadr)
					{
						if (!filter_var($mailadr, FILTER_VALIDATE_EMAIL))
						{
							$errors[] =  $mailadr . ' is geen geldig email adres.';
						}

						$st->bindValue(1, $mailadr);

						if ($edit)
						{
							$st->bindValue(2, $edit);
						}

						$st->execute();

						$row = $st->fetch();

						$warning = 'Omdat deze gebruikers niet meer een uniek E-mail adres hebben zullen zij ';
						$warning .= 'niet meer zelf hun paswoord kunnnen resetten of kunnen inloggen met ';
						$warning .= 'E-mail adres. Zie ';
						$warning .= $app['link']->link_no_attr('status', $app['pp_ary'], [], 'Status');

						$warning_2 = '';

						if ($row['count'] == 1)
						{
							$warning_2 .= 'Waarschuwing: E-mail adres ' . $mailadr;
							$warning_2 .= ' bestaat al onder de actieve gebruikers. ';
						}
						else if ($row['count'] > 1)
						{
							$warning_2 .= 'Waarschuwing: E-mail adres ' . $mailadr;
							$warning_2 .= ' bestaat al ' . $row['count'];
							$warning_2 .= ' maal onder de actieve gebruikers. ';
						}

						if ($warning_2)
						{
							$app['alert']->warning($warning_2 . $warning);
						}
					}
				}
			}

			if ($user['status'] == 1 || $user['status'] == 2)
			{
				if (!$mailadr)
				{
					$err = 'Waarschuwing: Geen E-mail adres ingevuld. ';
					$err .= 'De gebruiker kan geen berichten en notificaties ';
					$err .= 'ontvangen en zijn/haar paswoord niet resetten.';
					$app['alert']->warning($err);
				}
			}

			$letscode_sql = 'select letscode
				from ' . $app['tschema'] . '.users
				where letscode = ?';
			$letscode_sql_params = [$user['letscode']];
		}

		if ($username_edit)
		{
			$user['login'] = $user['name'] = trim($_POST['name']);
		}

		if ($fullname_edit)
		{
			$user['fullname'] = trim($_POST['fullname']);
		}

		$fullname_access = $app['request']->request->get('fullname_access', '');

		$name_sql = 'select name
			from ' . $app['tschema'] . '.users
			where name = ?';
		$name_sql_params = [$user['name']];

		$fullname_sql = 'select fullname
			from ' . $app['tschema'] . '.users
			where fullname = ?';
		$fullname_sql_params = [$user['fullname']];

		if ($edit)
		{
			$letscode_sql .= ' and id <> ?';
			$letscode_sql_params[] = $edit;
			$name_sql .= 'and id <> ?';
			$name_sql_params[] = $edit;
			$fullname_sql .= 'and id <> ?';
			$fullname_sql_params[] = $edit;

			$user_prefetch = $app['user_cache']->get($edit, $app['tschema']);
		}

		if (!$fullname_access)
		{
			$errors[] = 'Vul een zichtbaarheid in voor de volledige naam.';
		}

		if ($username_edit)
		{
			if (!$user['name'])
			{
				$errors[] = 'Vul gebruikersnaam in!';
			}
			else if ($app['db']->fetchColumn($name_sql, $name_sql_params))
			{
				$errors[] = 'Deze gebruikersnaam is al in gebruik!';
			}
			else if (strlen($user['name']) > 50)
			{
				$errors[] = 'De gebruikersnaam mag maximaal 50 tekens lang zijn.';
			}
		}

		if ($fullname_edit)
		{
			if (!$user['fullname'])
			{
				$errors[] = 'Vul de Volledige Naam in!';
			}

			if ($app['db']->fetchColumn($fullname_sql, $fullname_sql_params))
			{
				$errors[] = 'Deze Volledige Naam is al in gebruik!';
			}

			if (strlen($user['fullname']) > 100)
			{
				$errors[] = 'De Volledige Naam mag maximaal 100 tekens lang zijn.';
			}
		}

		if ($app['pp_admin'])
		{
			if (!$user['letscode'])
			{
				$errors[] = 'Vul een Account Code in!';
			}
			else if ($app['db']->fetchColumn($letscode_sql, $letscode_sql_params))
			{
				$errors[] = 'De Account Code bestaat al!';
			}
			else if (strlen($user['letscode']) > 20)
			{
				$errors[] = 'De Account Code mag maximaal
					20 tekens lang zijn.';
			}

			if (!preg_match("/^[A-Za-z0-9-]+$/", $user['letscode']))
			{
				$errors[] = 'De Account Code kan enkel uit
					letters, cijfers en koppeltekens bestaan.';
			}

			if (filter_var($user['minlimit'], FILTER_VALIDATE_INT) === false)
			{
				$errors[] = 'Geef getal of niets op voor de
					Minimum Account Limiet.';
			}

			if (filter_var($user['maxlimit'], FILTER_VALIDATE_INT) === false)
			{
				$errors[] = 'Geef getal of niets op voor de
					Maximum Account Limiet.';
			}

			if (strlen($user['presharedkey']) > 80)
			{
				$errors[] = 'De Preshared Key mag maximaal
					80 tekens lang zijn.';
			}
		}

		if ($user['birthday'])
		{
			$user['birthday'] = $app['date_format']->reverse($user['birthday'], $app['tschema']);

			if ($user['birthday'] === '')
			{
				$errors[] = 'Fout in formaat geboortedag.';
				$user['birthday'] = '';
			}
		}

		if (strlen($user['comments']) > 100)
		{
			$errors[] = 'Het veld Commentaar mag maximaal
				100 tekens lang zijn.';
		}

		if (strlen($user['postcode']) > 6)
		{
			$errors[] = 'De postcode mag maximaal 6 tekens lang zijn.';
		}

		if (strlen($user['hobbies']) > 500)
		{
			$errors[] = 'Het veld hobbies en interesses mag
				maximaal 500 tekens lang zijn.';
		}

		if ($app['pp_admin'] && !$user_prefetch['adate'] && $user['status'] == 1)
		{
			if (!$password)
			{
				$errors[] = 'Gelieve een Paswoord in te vullen.';
			}
			else if (!$app['password_strength']->get($password))
			{
				$errors[] = 'Het Paswoord is niet sterk genoeg.';
			}
		}

		if ($error_token = $app['form_token']->get_error())
		{
			$errors[] = $error_token;
		}

		if (!count($errors))
		{
			$contact_types = [];

			$rs = $app['db']->prepare('select abbrev, id
				from ' . $app['tschema'] . '.type_contact');

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$contact_types[$row['abbrev']] = $row['id'];
			}

			if ($add)
			{
				$user['creator'] = $app['s_master'] ? 0 : $app['s_id'];

				$user['cdate'] = gmdate('Y-m-d H:i:s');

				if ($user['status'] == 1)
				{
					$user['adate'] = gmdate('Y-m-d H:i:s');
					$user['password'] = hash('sha512', $password);
				}
				else
				{
					$user['password'] = hash('sha512', sha1(microtime()));
				}

				if ($app['db']->insert($app['tschema'] . '.users', $user))
				{
					$id = $app['db']->lastInsertId($app['tschema'] . '.users_id_seq');

					$fullname_access_role = $app['item_access']->get_xdb($fullname_access);

					$app['xdb']->set('user_fullname_access', $id, [
						'fullname_access' => $fullname_access_role,
					], $app['tschema']);

					$app['alert']->success('Gebruiker opgeslagen.');

					$app['user_cache']->clear($id, $app['tschema']);
					$user = $app['user_cache']->get($id, $app['tschema']);

					foreach ($contact as $value)
					{
						if (!$value['value'])
						{
							continue;
						}

						if ($value['abbrev'] === 'adr')
						{
							$app['queue.geocode']->cond_queue([
								'adr'		=> $value['value'],
								'uid'		=> $id,
								'schema'	=> $app['tschema'],
							], 0);
						}

						$insert = [
							'value'				=> trim($value['value']),
							'flag_public'		=> $value['flag_public'],
							'id_type_contact'	=> $contact_types[$value['abbrev']],
							'id_user'			=> $id,
						];

						$app['db']->insert($app['tschema'] . '.contact', $insert);
					}

					if ($user['status'] == 1)
					{
						if ($notify && $password)
						{
							if ($app['config']->get('mailenabled', $app['tschema']))
							{
								if ($mailadr)
								{
									send_activation_mail_user($id, $password);
									$app['alert']->success('Een E-mail met paswoord is
										naar de gebruiker verstuurd.');
								}
								else
								{
									$app['alert']->warning('Er is geen E-mail met paswoord
										naar de gebruiker verstuurd want er is geen E-mail
										adres ingesteld voor deze gebruiker.');
								}

								send_activation_mail_admin($id);

							}
							else
							{
								$app['alert']->warning('De E-mail functies zijn uitgeschakeld.
									Geen E-mail met paswoord naar de gebruiker verstuurd.');
							}
						}
						else
						{
							$app['alert']->warning('Geen E-mail met paswoord naar
								de gebruiker verstuurd.');
						}
					}

					if ($user['status'] == 2 | $user['status'] == 1)
					{
						delete_thumbprint('active');
					}

					if ($user['status'] == 7)
					{
						delete_thumbprint('extern');
					}

					$app['intersystems']->clear_cache($app['s_schema']);

					$app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
				}
				else
				{
					$app['alert']->error('Gebruiker niet opgeslagen.');
				}
			}
			else if ($edit)
			{
				$user_stored = $app['user_cache']->get($edit, $app['tschema']);

				$user['mdate'] = gmdate('Y-m-d H:i:s');

				if (!$user_stored['adate'] && $user['status'] == 1)
				{
					$user['adate'] = gmdate('Y-m-d H:i:s');

					if ($password)
					{
						$user['password'] = hash('sha512', $password);
					}
				}

				if($app['db']->update($app['tschema'] . '.users', $user, ['id' => $edit]))
				{

					$fullname_access_role = $app['item_access']->get_xdb($fullname_access);

					$app['xdb']->set('user_fullname_access', $edit, [
						'fullname_access' => $fullname_access_role,
					], $app['tschema']);

					$app['user_cache']->clear($edit, $app['tschema']);
					$user = $app['user_cache']->get($edit, $app['tschema']);

					$app['alert']->success('Gebruiker aangepast.');

					if ($app['pp_admin'])
					{
						$stored_contacts = [];

						$rs = $app['db']->prepare('select c.id,
								tc.abbrev, c.value, c.flag_public
							from ' . $app['tschema'] . '.type_contact tc, ' .
								$app['tschema'] . '.contact c
							WHERE tc.id = c.id_type_contact
								AND c.id_user = ?');
						$rs->bindValue(1, $edit);

						$rs->execute();

						while ($row = $rs->fetch())
						{
							$stored_contacts[$row['id']] = $row;
						}

						foreach ($contact as $value)
						{
							$stored_contact = $stored_contacts[$value['id']];

							if (!$value['value'])
							{
								if ($stored_contact)
								{
									$app['db']->delete($app['tschema'] . '.contact',
										['id_user' => $edit, 'id' => $value['id']]);
								}
								continue;
							}

							if ($stored_contact['abbrev'] == $value['abbrev']
								&& $stored_contact['value'] == $value['value']
								&& $stored_contact['flag_public'] == $value['flag_public'])
							{
								continue;
							}

							if ($value['abbrev'] === 'adr')
							{
								$app['queue.geocode']->cond_queue([
									'adr'		=> $value['value'],
									'uid'		=> $edit,
									'schema'	=> $app['tschema'],
								], 0);
							}

							if (!isset($stored_contact))
							{
								$insert = [
									'id_type_contact'	=> $contact_types[$value['abbrev']],
									'value'				=> trim($value['value']),
									'flag_public'		=> $value['flag_public'],
									'id_user'			=> $edit,
								];
								$app['db']->insert($app['tschema'] . '.contact', $insert);
								continue;
							}

							$contact_update = $value;

							unset($contact_update['id'], $contact_update['abbrev'],
								$contact_update['name'], $contact_update['main_mail']);

							$app['db']->update($app['tschema'] . '.contact',
								$contact_update,
								['id' => $value['id'], 'id_user' => $edit]);
						}

						if ($user['status'] == 1 && !$user_prefetch['adate'])
						{
							if ($notify && $password)
							{
								if ($app['config']->get('mailenabled', $app['tschema']))
								{
									if ($mailadr)
									{
										send_activation_mail_user($edit, $password);
										$app['alert']->success('E-mail met paswoord
											naar de gebruiker verstuurd.');
									}
									else
									{
										$app['alert']->warning('Er werd geen E-mail
											met passwoord naar de gebruiker verstuurd
											want er is geen E-mail adres voor deze
											gebruiker ingesteld.');
									}

									send_activation_mail_admin($edit);
								}
								else
								{
									$app['alert']->warning('De E-mail functies zijn uitgeschakeld.
										Geen E-mail met paswoord naar de gebruiker verstuurd.');
								}
							}
							else
							{
								$app['alert']->warning('Geen E-mail met
									paswoord naar de gebruiker verstuurd.');
							}
						}

						if ($user['status'] == 1
							|| $user['status'] == 2
							|| $user_stored['status'] == 1
							|| $user_stored['status'] == 2)
						{
							delete_thumbprint('active');
						}

						if ($user['status'] == 7
							|| $user_stored['status'] == 7)
						{
							delete_thumbprint('extern');
						}

						$app['intersystems']->clear_cache($app['s_schema']);
					}

					$app['link']->redirect('users', $app['pp_ary'], ['id' => $edit]);
				}
				else
				{
					$app['alert']->error('Gebruiker niet aangepast.');
				}
			}
		}
		else
		{
			$app['alert']->error($errors);

			if ($edit)
			{
				$user['adate'] = $user_prefetch['adate'];
			}

			$user['minlimit'] = $user['minlimit'] === -999999999 ? '' : $user['minlimit'];
			$user['maxlimit'] = $user['maxlimit'] === 999999999 ? '' : $user['maxlimit'];
		}
	}
	else
	{
		if ($edit)
		{
			$user = $app['user_cache']->get($edit, $app['tschema']);
			$fullname_access = $user['fullname_access'];
		}

		if ($app['pp_admin'])
		{
			$contact = $app['db']->fetchAll('select name, abbrev,
				\'\' as value, 0 as id
				from ' . $app['tschema'] . '.type_contact
				where abbrev in (\'mail\', \'adr\', \'tel\', \'gsm\')');
		}

		if ($edit && $app['pp_admin'])
		{
			$contact_keys = [];

			foreach ($contact as $key => $c)
			{
				$contact_keys[$c['abbrev']] = $key;
			}

			$st = $app['db']->prepare('select tc.abbrev, c.value, tc.name, c.flag_public, c.id
				from ' . $app['tschema'] . '.type_contact tc, ' .
					$app['tschema'] . '.contact c
				where tc.id = c.id_type_contact
					and c.id_user = ?');

			$st->bindValue(1, $edit);
			$st->execute();

			while ($row = $st->fetch())
			{
				if (isset($contact_keys[$row['abbrev']]))
				{
					$contact[$contact_keys[$row['abbrev']]] = $row;
					unset($contact_keys[$row['abbrev']]);
					continue;
				}

				$contact[] = $row;
			}
		}
		else if ($app['pp_admin'])
		{
			$user = [
				'minlimit'		=> $app['config']->get('preset_minlimit', $app['tschema']),
				'maxlimit'		=> $app['config']->get('preset_maxlimit', $app['tschema']),
				'accountrole'	=> 'user',
				'status'		=> '1',
				'cron_saldo'	=> 1,
			];

			if ($intersystem_code)
			{
				if ($group = $app['db']->fetchAssoc('select *
					from ' . $app['tschema'] . '.letsgroups
					where localletscode = ?
						and apimethod <> \'internal\'', [$intersystem_code]))
				{
					$user['name'] = $user['fullname'] = $group['groupname'];

					if ($group['url']
						&& ($app['systems']->get_schema_from_legacy_eland_origin($group['url'])))
					{
						$remote_schema = $app['systems']->get_schema_from_legacy_eland_origin($group['url']);

						$admin_mail = $app['config']->get('admin', $remote_schema);

						foreach ($contact as $k => $c)
						{
							if ($c['abbrev'] == 'mail')
							{
								$contact[$k]['value'] = $admin_mail;
								break;
							}
						}

						// name from source is preferable
						$user['name'] = $user['fullname'] = $app['config']->get('systemname', $remote_schema);
					}
				}

				$user['cron_saldo'] = 0;
				$user['status'] = '7';
				$user['accountrole'] = 'interlets';
				$user['letscode'] = $intersystem_code;
			}
			else
			{
				$user['cron_saldo'] = 1;
				$user['status'] = '1';
				$user['accountrole'] = 'user';
			}
		}
	}

	if ($edit)
	{
		$edit_user_cached = $app['user_cache']->get($edit, $app['tschema']);
	}

	array_walk($user, function(&$value, $key){ $value = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')); });
	array_walk($contact, function(&$value, $key){ $value['value'] = trim(htmlspecialchars($value['value'], ENT_QUOTES, 'UTF-8')); });

	$app['assets']->add([
		'datepicker',
		'generate_password.js',
		'generate_password_onload.js',
		'user_edit.js',
	]);

	if ($s_owner && !$app['pp_admin'] && $edit)
	{
		$app['heading']->add('Je profiel aanpassen');
	}
	else
	{
		$app['heading']->add('Gebruiker ');

		if ($edit)
		{
			$app['heading']->add('aanpassen: ');
			$app['heading']->add($app['account']->link($edit, $app['pp_ary']));
		}
		else
		{
			$app['heading']->add('toevoegen');
		}
	}

	$app['heading']->fa('user');

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	if ($app['pp_admin'])
	{
		echo '<div class="form-group">';
		echo '<label for="letscode" class="control-label">';
		echo 'Account Code';
		echo '</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<span class="fa fa-user"></span></span>';
		echo '<input type="text" class="form-control" ';
		echo 'id="letscode" name="letscode" ';
		echo 'value="';
		echo $user['letscode'] ?? '';
		echo '" required maxlength="20" ';
		echo 'data-typeahead="';

		echo $app['typeahead']->ini($app['pp_ary'])
			->add('account_codes', [])
			->str([
				'render'	=> [
					'check'	=> 10,
					'omit'	=> $edit_user_cached['letscode'] ?? '',
				]
			]);

		echo '">';
		echo '</div>';
		echo '<span class="help-block hidden exists_query_results">';
		echo 'Reeds gebruikt: ';
		echo '<span class="query_results">';
		echo '</span>';
		echo '</span>';
		echo '<span class="help-block hidden exists_msg">';
		echo 'Deze Account Code bestaat al!';
		echo '</span>';
		echo '</div>';
	}

	if ($username_edit)
	{
		echo '<div class="form-group">';
		echo '<label for="name" class="control-label">';
		echo 'Gebruikersnaam</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<span class="fa fa-user"></span></span>';
		echo '<input type="text" class="form-control" ';
		echo 'id="name" name="name" ';
		echo 'value="';
		echo $user['name'] ?? '';
		echo '" required maxlength="50" ';
		echo 'data-typeahead="';

		echo $app['typeahead']->ini($app['pp_ary'])
			->add('usernames', [])
			->str([
				'render'	=> [
					'check'	=> 10,
					'omit'	=> $edit_user_cached['name'] ?? '',
				]
			]);

		echo '">';
		echo '</div>';
		echo '<span class="help-block hidden exists_query_results">';
		echo 'Reeds gebruikt: ';
		echo '<span class="query_results">';
		echo '</span>';
		echo '</span>';
		echo '<span id="username_exists" ';
		echo 'class="help-block hidden exists_msg">';
		echo 'Deze Gebruikersnaam bestaat reeds!</span>';
		echo '</div>';
	}

	if ($fullname_edit)
	{
		echo '<div class="form-group">';
		echo '<label for="fullname" class="control-label">';
		echo 'Volledige Naam</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<span class="fa fa-user"></span></span>';
		echo '<input type="text" class="form-control" ';
		echo 'id="fullname" name="fullname" ';
		echo 'value="';
		echo $user['fullname'] ?? '';
		echo '" maxlength="100">';
		echo '</div>';
		echo '<p>';
		echo 'Voornaam en Achternaam';
		echo '</p>';
		echo '</div>';
	}

	if (!isset($fullname_access))
	{
		$fullname_access = $add && !$intersystem_code ? '' : 'admin';
	}

	echo $app['item_access']->get_radio_buttons(
		'users_fullname',
		$fullname_access,
		'fullname_access',
		false,
		'Zichtbaarheid Volledige Naam'
	);

	echo '<div class="form-group">';
	echo '<label for="postcode" class="control-label">';
	echo 'Postcode</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-map-marker"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="postcode" name="postcode" ';
	echo 'value="';
	echo $user['postcode'] ?? '';
	echo '" ';
	echo 'required maxlength="6" ';
	echo 'data-typeahead="';

	echo $app['typeahead']->ini($app['pp_ary'])
		->add('postcodes', [])
		->str();

	echo '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="birthday" class="control-label">';
	echo 'Geboortedatum</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-calendar"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="birthday" name="birthday" ';
	echo 'value="';

	if (isset($user['birthday']) && !empty($user['birtday']))
	{
		echo $app['date_format']->get($user['birthday'], 'day', $app['tschema']);
	}

	echo '" ';
	echo 'data-provide="datepicker" ';
	echo 'data-date-format="';
	echo $app['date_format']->datepicker_format($app['tschema']);
	echo '" ';
	echo 'data-date-default-view="2" ';
	echo 'data-date-end-date="';
	echo $app['date_format']->get('', 'day', $app['tschema']);
	echo '" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-start-view="2" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-immediate-updates="true" ';
	echo 'data-date-orientation="bottom" ';
	echo 'placeholder="';
	echo $app['date_format']->datepicker_placeholder($app['tschema']);
	echo '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="hobbies" class="control-label">';
	echo 'Hobbies, interesses</label>';
	echo '<textarea name="hobbies" id="hobbies" ';
	echo 'class="form-control" maxlength="500">';
	echo $user['hobbies'] ?? '';
	echo '</textarea>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="comments" class="control-label">Commentaar</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-comment-o"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="comments" name="comments" ';
	echo 'value="';
	echo $user['comments'] ?? '';
	echo '">';
	echo '</div>';
	echo '</div>';

	if ($app['pp_admin'])
	{
		echo '<div class="form-group">';
		echo '<label for="accountrole" class="control-label">';
		echo 'Rechten / Rol</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<span class="fa fa-hand-paper-o"></span></span>';
		echo '<select id="accountrole" name="accountrole" ';
		echo 'class="form-control">';
		echo $app['select']->get_options(cnst_role::LABEL_ARY, $user['accountrole']);
		echo '</select>';
		echo '</div>';
		echo '</div>';

		echo '<div class="pan-sub" id="presharedkey_panel">';
		echo '<div class="form-group" id="presharedkey_formgroup">';
		echo '<label for="presharedkey" class="control-label">';
		echo 'Preshared Key</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<span class="fa fa-key"></span></span>';
		echo '<input type="text" class="form-control" ';
		echo 'id="presharedkey" name="presharedkey" ';
		echo 'value="';
		echo $user['presharedkey'] ?? '';
		echo '" maxlength="80">';
		echo '</div>';
		echo '<p>Vul dit enkel in voor een interSysteem Account ';
		echo 'van een Systeem op een eLAS-server.</p>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="status" class="control-label">';
		echo 'Status</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<span class="fa fa-star-o"></span></span>';
		echo '<select id="status" name="status" class="form-control">';
		echo $app['select']->get_options(cnst_status::LABEL_ARY, $user['status']);
		echo '</select>';
		echo '</div>';
		echo '</div>';

		if (empty($user['adate']) && $app['pp_admin'])
		{
			echo '<div id="activate" class="bg-success pan-sub">';

			echo '<div class="form-group">';
			echo '<label for="password" class="control-label">';
			echo 'Paswoord</label>';
			echo '<div class="input-group">';
			echo '<span class="input-group-addon">';
			echo '<span class="fa fa-key"></span></span>';
			echo '<input type="text" class="form-control" ';
			echo 'id="password" name="password" ';
			echo 'value="';
			echo $password ?? '';
			echo '" required>';
			echo '<span class="input-group-btn">';
			echo '<button class="btn btn-default" ';
			echo 'type="button" id="generate">';
			echo 'Genereer</button>';
			echo '</span>';
			echo '</div>';
			echo '</div>';

			echo '<div class="form-group">';
			echo '<label for="notify" class="control-label">';
			echo '<input type="checkbox" name="notify" id="notify"';
			echo ' checked="checked"';
			echo '> ';
			echo 'Verstuur een E-mail met het ';
			echo 'paswoord naar de gebruiker. ';
			echo 'Dit kan enkel wanneer het account ';
			echo 'de status actief heeft en ';
			echo 'een E-mail adres is ingesteld.';
			echo '</label>';
			echo '</div>';

			echo '</div>';
		}

		echo '<div class="form-group">';
		echo '<label for="admincomment" class="control-label">';
		echo 'Commentaar van de admin</label>';
		echo '<textarea name="admincomment" id="admincomment" ';
		echo 'class="form-control" maxlength="200">';
		echo $user['admincomment'] ?? '';
		echo '</textarea>';
		echo '</div>';

		echo '<div class="pan-sub">';

		echo '<h2>Limieten&nbsp;';

		if ($user['minlimit'] === '' && $user['maxlimit'] === '')
		{
			echo '<button class="btn btn-default" ';
			echo 'title="Limieten instellen" data-toggle="collapse" ';
			echo 'data-target="#limits_pan" type="button">';
			echo 'Instellen</button>';
		}

		echo '</h2>';

		echo '<div id="limits_pan"';

		if ($user['minlimit'] === '' && $user['maxlimit'] === '')
		{
			echo ' class="collapse"';
		}

		echo '>';

		echo '<div class="form-group">';
		echo '<label for="minlimit" class="control-label">';
		echo 'Minimum Account Limiet</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<span class="fa fa-arrow-down"></span> ';
		echo $app['config']->get('currency', $app['tschema']);
		echo '</span>';
		echo '<input type="number" class="form-control" ';
		echo 'id="minlimit" name="minlimit" ';
		echo 'value="';
		echo $user['minlimit'] ?? '';
		echo '">';
		echo '</div>';
		echo '<p>Vul enkel in wanneer je een individueel ';
		echo 'afwijkende minimum limiet wil instellen ';
		echo 'voor dit account. Als dit veld leeg is, ';
		echo 'dan is de algemeen geldende ';
		echo $app['link']->link_no_attr('config', $app['pp_ary'],
			['tab' => 'balance'], 'Minimum Systeemslimiet');
		echo ' ';
		echo 'van toepassing. ';

		if ($app['config']->get('minlimit', $app['tschema']) === '')
		{
			echo 'Er is momenteel <strong>geen</strong> algemeen ';
			echo 'geledende Minimum Systeemslimiet ingesteld. ';
		}
		else
		{
			echo 'De algemeen geldende ';
			echo 'Minimum Systeemslimiet bedraagt <strong>';
			echo $app['config']->get('minlimit', $app['tschema']);
			echo ' ';
			echo $app['config']->get('currency', $app['tschema']);
			echo '</strong>. ';
		}

		echo 'Dit veld wordt bij aanmaak van een ';
		echo 'gebruiker vooraf ingevuld met de "';
		echo $app['link']->link_no_attr('config', $app['pp_ary'],
			['tab' => 'balance'],
			'Preset Individuele Minimum Account Limiet');
		echo '" ';
		echo 'die gedefiniëerd is in de instellingen.';

		if ($app['config']->get('preset_minlimit', $app['tschema']) !== '')
		{
			echo ' De Preset bedraagt momenteel <strong>';
			echo $app['config']->get('preset_minlimit', $app['tschema']);
			echo '</strong>.';
		}

		echo '</p>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="maxlimit" class="control-label">';
		echo 'Maximum Account Limiet</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<span class="fa fa-arrow-up"></span> ';
		echo $app['config']->get('currency', $app['tschema']);
		echo '</span>';
		echo '<input type="number" class="form-control" ';
		echo 'id="maxlimit" name="maxlimit" ';
		echo 'value="';
		echo $user['maxlimit'] ?? '';
		echo '">';
		echo '</div>';

		echo '<p>Vul enkel in wanneer je een individueel ';
		echo 'afwijkende maximum limiet wil instellen ';
		echo 'voor dit account. Als dit veld leeg is, ';
		echo 'dan is de algemeen geldende ';
		echo $app['link']->link_no_attr('config', $app['pp_ary'],
			['tab' => 'balance'],
			'Maximum Systeemslimiet');
		echo ' ';
		echo 'van toepassing. ';

		if ($app['config']->get('maxlimit', $app['tschema']) === '')
		{
			echo 'Er is momenteel <strong>geen</strong> algemeen ';
			echo 'geledende Maximum Systeemslimiet ingesteld. ';
		}
		else
		{
			echo 'De algemeen geldende Maximum ';
			echo 'Systeemslimiet bedraagt <strong>';
			echo $app['config']->get('maxlimit', $app['tschema']);
			echo ' ';
			echo $app['config']->get('currency', $app['tschema']);
			echo '</strong>. ';
		}

		echo 'Dit veld wordt bij aanmaak van een gebruiker ';
		echo 'vooraf ingevuld wanneer "';
		echo $app['link']->link_no_attr('config', $app['pp_ary'],
			['tab' => 'balance'],
			'Preset Individuele Maximum Account Limiet');
		echo '" ';
		echo 'is ingevuld in de instellingen.';

		if ($app['config']->get('preset_maxlimit', $app['tschema']) !== '')
		{
			echo ' De Preset bedraagt momenteel <strong>';
			echo $app['config']->get('preset_maxlimit', $app['tschema']);
			echo '</strong>.';
		}

		echo '</p>';

		echo '</div>';
		echo '</div>';
		echo '</div>';

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
				'disabled'	=> true,     // Prevent browser fill-in, removed by js.
			],
			'web'	=> [
				'fa'		=> 'link',
				'lbl'		=> 'Website',
				'type'		=> 'url',
			],
		];

		echo '<div class="bg-warning pan-sub">';
		echo '<h2><i class="fa fa-map-marker"></i> Contacten</h2>';

		echo '<p>Meer contacten kunnen toegevoegd worden ';
		echo 'vanuit de profielpagina met de knop ';
		echo 'Toevoegen bij de contactinfo ';
		echo $add ? 'nadat de gebruiker gecreëerd is' : '';
		echo '.</p>';

		foreach ($contact as $key => $c)
		{
			$name = 'contact[' . $key . '][value]';

			echo '<div class="pan-sab">';

			echo '<div class="form-group">';
			echo '<label for="';
			echo $name;
			echo '" class="control-label">';
			echo $contacts_format[$c['abbrev']]['lbl'] ?? $c['abbrev'];
			echo '</label>';
			echo '<div class="input-group">';
			echo '<span class="input-group-addon">';
			echo '<i class="fa fa-';
			echo $contacts_format[$c['abbrev']]['fa'] ?? 'question-mark';
			echo '"></i>';
			echo '</span>';
			echo '<input class="form-control" id="';
			echo $name;
			echo '" name="';
			echo $name;
			echo '" ';
			echo 'value="';
			echo $c['value'] ?? '';
			echo '" type="';
			echo $contacts_format[$c['abbrev']]['type'] ?? 'text';
			echo '" ';
			echo isset($contacts_format[$c['abbrev']]['disabled']) ? 'disabled ' : '';
			echo 'data-access="contact_access_' . $key . '">';
			echo '</div>';
			echo '<p>';
			echo $contacts_format[$c['abbrev']]['explain'] ?? '';
			echo '</p>';
			echo '</div>';

			echo $app['item_access']->get_radio_buttons(
				$c['abbrev'],
				$app['item_access']->get_value_from_flag_public($c['flag_public']),
				'contact_access_' . $key
			);

			echo '<input type="hidden" ';
			echo 'name="contact['. $key . '][id]" value="' . $c['id'] . '">';
			echo '<input type="hidden" ';
			echo 'name="contact['. $key . '][name]" value="' . $c['name'] . '">';
			echo '<input type="hidden" ';
			echo 'name="contact['. $key . '][abbrev]" value="' . $c['abbrev'] . '">';

			echo '</div>';
		}

		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<label for="cron_saldo" class="control-label">';
	echo '<input type="checkbox" name="cron_saldo" id="cron_saldo"';
	echo $user['cron_saldo'] ? ' checked="checked"' : '';
	echo '>	';
	echo 'Periodieke Overzichts E-mail';
	echo '</label>';
	echo '</div>';

	$btn = $edit ? 'primary' : 'success';

	echo $app['link']->btn_cancel('users', $app['pp_ary'],
		$edit ? ['id' => $edit] : ['status' => 'active']);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Opslaan" class="btn btn-';
	echo $btn . '">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * status definitions
 */

$st = [
	'active'	=> [
		'lbl'	=> $app['pp_admin'] ? 'Actief' : 'Alle',
		'sql'	=> 'u.status in (1, 2)',
		'st'	=> [1, 2],
	],
	'new'		=> [
		'lbl'	=> 'Instappers',
		'sql'	=> 'u.status = 1 and u.adate > ?',
		'sql_bind'	=> gmdate('Y-m-d H:i:s', $app['new_user_treshold']),
		'cl'	=> 'success',
		'st'	=> 3,
	],
	'leaving'	=> [
		'lbl'	=> 'Uitstappers',
		'sql'	=> 'u.status = 2',
		'cl'	=> 'danger',
		'st'	=> 2,
	],
];

if ($app['pp_admin'])
{
	$st = $st + [
		'inactive'	=> [
			'lbl'	=> 'Inactief',
			'sql'	=> 'u.status = 0',
			'cl'	=> 'inactive',
			'st'	=> 0,
		],
		'ip'		=> [
			'lbl'	=> 'Info-pakket',
			'sql'	=> 'u.status = 5',
			'cl'	=> 'warning',
			'st'	=> 5,
		],
		'im'		=> [
			'lbl'	=> 'Info-moment',
			'sql'	=> 'u.status = 6',
			'cl'	=> 'info',
			'st'	=> 6
		],
		'extern'	=> [
			'lbl'	=> 'Extern',
			'sql'	=> 'u.status = 7',
			'cl'	=> 'extern',
			'st'	=> 7,
		],
		'all'		=> [
			'lbl'	=> 'Alle',
			'sql'	=> '1 = 1',
		],
	];
}

/*
 * Show a user
 */

if ($id)
{
	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $app['s_id'] == $id
		&& $id;

	$user_mail_cc = $app['request']->isMethod('POST') ? $user_mail_cc : 1;

	$user = $app['user_cache']->get($id, $app['tschema']);

	if (!$user)
	{
		$app['alert']->error('Er bestaat geen gebruiker met id ' . $id . '.');
		cancel();
	}

	if (!$app['pp_admin'] && !in_array($user['status'], [1, 2]))
	{
		$app['alert']->error('Je hebt geen toegang tot deze gebruiker.');

		$app['link']->redirect('users', $app['pp_ary'], []);
	}

	if ($app['pp_admin'])
	{
		$count_transactions = $app['db']->fetchColumn('select count(*)
			from ' . $app['tschema'] . '.transactions
			where id_from = ?
				or id_to = ?', [$id, $id]);
	}

	$mail_to = $app['mail_addr_user']->get($user['id'], $app['tschema']);
	$mail_from = $app['s_schema']
		&& !$app['s_master']
		&& !$app['s_elas_guest']
			? $app['mail_addr_user']->get($app['s_id'], $app['s_schema'])
			: [];

	$sql_bind = [$user['letscode']];

	if ($link && isset($st[$link]))
	{
		$and_status = isset($st[$link]['sql'])
			? ' and ' . $st[$link]['sql']
			: '';

		if (isset($st[$link]['sql_bind']))
		{
			$sql_bind[] = $st[$link]['sql_bind'];
		}
	}
	else
	{
		$and_status = $app['pp_admin'] ? '' : ' and u.status in (1, 2) ';
	}

	$next = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.users u
		where u.letscode > ?
		' . $and_status . '
		order by u.letscode asc
		limit 1', $sql_bind);

	$prev = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.users u
		where u.letscode < ?
		' . $and_status . '
		order by u.letscode desc
		limit 1', $sql_bind);

	$intersystem_missing = false;

	if ($app['pp_admin']
		&& $user['accountrole'] === 'interlets'
		&& $app['intersystem_en'])
	{
		$intersystem_id = $app['db']->fetchColumn('select id
			from ' . $app['tschema'] . '.letsgroups
			where localletscode = ?', [$user['letscode']]);

		if (!$intersystem_id)
		{
			$intersystem_missing = true;
		}
	}
	else
	{
		$intersystem_id = false;
	}

	$app['assets']->add([
		'leaflet',
		'jqplot',
		'user.js',
		'plot_user_transactions.js',
	]);

	if ($app['pp_admin'] || $s_owner)
	{
		$app['assets']->add([
			'fileupload',
			'user_img.js',
		]);
	}

	if ($app['pp_admin'] || $s_owner)
	{
		$title = $app['pp_admin'] ? 'Gebruiker' : 'Mijn gegevens';

		$app['btn_top']->edit('users', $app['pp_ary'],
			['edit' => $id], $title . ' aanpassen');

		$app['btn_top']->edit_pw('users', $app['pp_ary'],
			['pw' => $id], 'Paswoord aanpassen');
	}

	if ($app['pp_admin'] && !$count_transactions && !$s_owner)
	{
		$app['btn_top']->del('users', $app['pp_ary'],
			['del' => $id], 'Gebruiker verwijderen');
	}

	if ($app['pp_admin']
		|| (!$s_owner && $user['status'] !== 7
			&& !($app['s_guest'] && $app['s_system_self'])))
	{
		$tus = ['add' => 1, 'tuid' => $id];

		if (!$app['s_system_self'])
		{
			$tus['tus'] = $app['tschema'];
		}

		$app['btn_top']->add_trans('transactions', $app['s_ary'],
			$tus, 'Transactie naar ' . $app['account']->str($id, $app['tschema']));
	}

	$link_ary = $link ? ['link' => $link] : [];
	$prev_ary = $prev ? array_merge($link_ary, ['id' => $prev]) : [];
	$next_ary = $next ? array_merge($link_ary, ['id' => $next]) : [];

	$app['btn_nav']->nav('users', $app['pp_ary'],
		$prev_ary, $next_ary, false);

	$app['btn_nav']->nav_list('users', $app['pp_ary'],
		['link' => $link], 'Overzicht', 'users');

	$status = $user['status'];

	if (isset($user['adate']))
	{
		$status = ($app['new_user_treshold'] < strtotime($user['adate']) && $status == 1) ? 3 : $status;
	}

	$h_status_ary = cnst_status::LABEL_ARY;
	$h_status_ary[3] = 'Instapper';

	if ($s_owner && !$app['pp_admin'])
	{
		$app['heading']->add('Mijn gegevens: ');
	}

	$app['heading']->add($app['account']->link($id, $app['pp_ary']));

	if ($status != 1)
	{
		$app['heading']->add(' <small><span class="text-');
		$app['heading']->add(cnst_status::CLASS_ARY[$status]);
		$app['heading']->add('">');
		$app['heading']->add($h_status_ary[$status]);
		$app['heading']->add('</span></small>');
	}

	if ($app['pp_admin'])
	{
		if ($intersystem_missing)
		{
			$app['heading']->add(' <span class="label label-warning label-sm">');
			$app['heading']->add('<i class="fa fa-exclamation-triangle"></i> ');
			$app['heading']->add('De interSysteem-verbinding ontbreekt</span>');
		}
		else if ($intersystem_id)
		{
			$app['heading']->add(' ');
			$app['heading']->add($app['link']->link_fa('intersystem', $app['pp_ary'],
				['id' => $intersystem_id], 'Gekoppeld interSysteem',
				['class' => 'btn btn-default'], 'share-alt'));
		}
	}

	$app['heading']->fa('user');

	include __DIR__ . '/include/header.php';

	echo '<div class="row">';
	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-body text-center ';
	echo 'center-block" id="img_user">';

	$show_img = $user['PictureFile'] ? true : false;

	$user_img = $show_img ? '' : ' style="display:none;"';
	$no_user_img = $show_img ? ' style="display:none;"' : '';

	echo '<img id="user_img"';
	echo $user_img;
	echo ' class="img-rounded img-responsive center-block" ';
	echo 'src="';

	if ($user['PictureFile'])
	{
		echo $app['s3_url'] . $user['PictureFile'];
	}
	else
	{
		echo $app['rootpath'] . 'gfx/1.gif';
	}

	echo '" ';
	echo 'data-bucket-url="' . $app['s3_url'] . '"></img>';

	echo '<div id="no_user_img"';
	echo $no_user_img;
	echo '>';
	echo '<i class="fa fa-user fa-5x text-muted"></i>';
	echo '<br>Geen profielfoto</div>';

	echo '</div>';

	if ($app['pp_admin'] || $s_owner)
	{
		$attr = ['id'	=> 'btn_remove'];

		if (!$user['PictureFile'])
		{
			$attr['style'] = 'display:none;';
		}

		echo '<div class="panel-footer">';
		echo '<span class="btn btn-success fileinput-button">';
		echo '<i class="fa fa-plus" id="img_plus"></i> Foto opladen';
		echo '<input id="fileupload" type="file" name="image" ';
		echo 'data-url="';

		echo $app['link']->context_path('users', $app['pp_ary'],
			['img' => 1, 'id' => $id]);

		echo '" ';
		echo 'data-data-type="json" data-auto-upload="true" ';
		echo 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
		echo 'data-max-file-size="999000" data-image-max-width="400" ';
		echo 'data-image-crop="true" ';
		echo 'data-image-max-height="400"></span>&nbsp;';

		echo $app['link']->link_fa('users', $app['pp_ary'],
			['img_del' => 1, 'id' => $id],
			'Foto verwijderen',
			array_merge($attr, ['class' => 'btn btn-danger']),
			'times');

		echo '<p class="text-warning">';
		echo 'Je foto moet in het jpg/jpeg formaat zijn. ';
		echo 'Je kan ook een foto hierheen verslepen.</p>';
		echo '</div>';
	}

	echo '</div></div>';

	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';
	echo '<dl>';

	$fullname_access = $user['fullname_access'] ?: 'admin';

	echo '<dt>';
	echo 'Volledige naam';
	echo '</dt>';

	if ($app['pp_admin']
		|| $s_owner
		|| $app['item_access']->is_visible_xdb($fullname_access))
	{
		echo get_dd($user['fullname'] ?? '');
	}
	else
	{
		echo '<dd>';
		echo '<span class="btn btn-default">';
		echo 'verborgen</span>';
		echo '</dd>';
	}

	if ($app['pp_admin'])
	{
		echo '<dt>';
		echo 'Zichtbaarheid Volledige Naam';
		echo '</dt>';
		echo '<dd>';
		echo $app['item_access']->get_label_xdb($fullname_access);
		echo '</dd>';
	}

	echo '<dt>';
	echo 'Postcode';
	echo '</dt>';
	echo get_dd($user['postcode'] ?? '');

	if ($app['pp_admin'] || $s_owner)
	{
		echo '<dt>';
		echo 'Geboortedatum';
		echo '</dt>';
		if (isset($user['birthday']))
		{
			echo $app['date_format']->get($user['birthday'], 'day', $app['tschema']);
		}
		else
		{
			echo '<dd><i class="fa fa-times"></i></dd>';
		}
	}

	echo '<dt>';
	echo 'Hobbies / Interesses';
	echo '</dt>';
	echo get_dd($user['hobbies'] ?? '');

	echo '<dt>';
	echo 'Commentaar';
	echo '</dt>';
	echo get_dd($user['comments'] ?? '');

	if ($app['pp_admin'])
	{
		echo '<dt>';
		echo 'Tijdstip aanmaak';
		echo '</dt>';

		if (isset($user['cdate']))
		{
			echo get_dd($app['date_format']->get($user['cdate'], 'min', $app['tschema']));
		}
		else
		{
			echo '<dd><i class="fa fa-times"></i></dd>';
		}

		echo '<dt>';
		echo 'Tijdstip activering';
		echo '</dt>';

		if (isset($user['adate']))
		{
			echo get_dd($app['date_format']->get($user['adate'], 'min', $app['tschema']));
		}
		else
		{
			echo '<dd><i class="fa fa-times"></i></dd>';
		}

		echo '<dt>';
		echo 'Laatste login';
		echo '</dt>';

		if (isset($user['lastlogin']))
		{
			echo get_dd($app['date_format']->get($user['lastlogin'], 'min', $app['tschema']));
		}
		else
		{
			echo '<dd><i class="fa fa-times"></i></dd>';
		}

		echo '<dt>';
		echo 'Rechten / rol';
		echo '</dt>';
		echo get_dd(cnst_role::LABEL_ARY[$user['accountrole']]);

		echo '<dt>';
		echo 'Status';
		echo '</dt>';
		echo get_dd(cnst_status::LABEL_ARY[$user['status']]);

		echo '<dt>';
		echo 'Commentaar van de admin';
		echo '</dt>';
		echo get_dd($user['admincomment'] ?? '');
	}

	echo '<dt>Saldo</dt>';
	echo '<dd>';
	echo '<span class="label label-info">';
	echo $user['saldo'];
	echo'</span>&nbsp;';
	echo $app['config']->get('currency', $app['tschema']);
	echo '</dd>';

	if ($user['minlimit'] !== '')
	{
		echo '<dt>Minimum limiet</dt>';
		echo '<dd>';
		echo '<span class="label label-danger">';
		echo $user['minlimit'];
		echo '</span>&nbsp;';
		echo $app['config']->get('currency', $app['tschema']);
		echo '</dd>';
	}

	if ($user['maxlimit'] !== '')
	{
		echo '<dt>Maximum limiet</dt>';
		echo '<dd>';
		echo '<span class="label label-success">';
		echo $user['maxlimit'];
		echo '</span>&nbsp;';
		echo $app['config']->get('currency', $app['tschema']);
		echo '</dd>';
	}

	if ($app['pp_admin'] || $s_owner)
	{
		echo '<dt>';
		echo 'Periodieke Overzichts E-mail';
		echo '</dt>';
		echo $user['cron_saldo'] ? 'Aan' : 'Uit';
		echo '</dl>';
	}

	echo '</div></div></div></div>';

	echo '<div id="contacts" ';
	echo 'data-url="';
	echo $app->path('contacts', array_merge(['pp_ary'], [
		'inline'	=> '1',
		'uid'		=> $message['id_user'],
	]));
	echo '"></div>';

	// response form

	if ($app['s_elas_guest'])
	{
		$placeholder = 'Als eLAS gast kan je niet het E-mail formulier gebruiken.';
	}
	else if ($s_owner)
	{
		$placeholder = 'Je kan geen E-mail berichten naar jezelf verzenden.';
	}
	else if (!count($mail_to))
	{
		$placeholder = 'Er is geen E-mail adres bekend van deze gebruiker.';
	}
	else if (!count($mail_from))
	{
		$placeholder = 'Om het E-mail formulier te gebruiken moet een E-mail adres ingesteld zijn voor je eigen Account.';
	}
	else
	{
		$placeholder = '';
	}

	$disabled = !$app['s_schema']
		|| !count($mail_to)
		|| !count($mail_from)
		|| $s_owner;

	echo '<h3><i class="fa fa-envelop-o"></i> ';
	echo 'Stuur een bericht naar ';
	echo  $app['account']->link($id, $app['pp_ary']);
	echo '</h3>';
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post"">';

	echo '<div class="form-group">';
	echo '<textarea name="user_mail_content" rows="6" placeholder="';
	echo $placeholder . '" ';
	echo 'class="form-control" required';
	echo $disabled ? ' disabled' : '';
	echo '>';
	echo $user_mail_content ?? '';
	echo '</textarea>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="user_mail_cc" class="control-label">';
	echo '<input type="checkbox" name="user_mail_cc" ';
	echo 'id="user_mail_cc" value="1"';
	echo $user_mail_cc ? ' checked="checked"' : '';
	echo '> Stuur een kopie naar mijzelf';
	echo '</label>';
	echo '</div>';

	echo '<input type="submit" name="user_mail_submit" ';
	echo 'value="Versturen" class="btn btn-default"';
	echo $disabled ? ' disabled' : '';
	echo '>';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	//

	echo '<div class="row">';
	echo '<div class="col-md-12">';

	echo '<h3>Saldo: <span class="label label-info">';
	echo $user['saldo'];
	echo '</span> ';
	echo $app['config']->get('currency', $app['tschema']);
	echo '</h3>';
	echo '</div></div>';

	echo '<div class="row print-hide">';
	echo '<div class="col-md-6">';
	echo '<div id="chartdiv" data-height="480px" data-width="960px" ';

	echo 'data-plot-user-transactions="';
	echo htmlspecialchars($app['link']->context_path('plot_user_transactions',
		$app['pp_ary'], ['user_id' => $id, 'days' => $tdays]));
	echo '" ';

	echo 'data-transactions-show="';
	echo htmlspecialchars($app['link']->context_path('transactions_show',
		$app['pp_ary'], ['id' => 1]));
	echo '" ';

	echo 'data-users-show="';
	echo htmlspecialchars($app['link']->context_path('users_show',
		$app['pp_ary'], ['id' => 1]));
	echo '" ';

	echo '"></div>';
	echo '</div>';
	echo '<div class="col-md-6">';
	echo '<div id="donutdiv" data-height="480px" ';
	echo 'data-width="960px"></div>';
	echo '<h4>Interacties laatste jaar</h4>';
	echo '</div>';
	echo '</div>';

	if ($user['status'] == 1 || $user['status'] == 2)
	{
		echo '<div id="messages" ';
		echo 'data-url="';
		echo $app->path('messages', array_merge($app['pp_ary'], [
			'inline'	=> '1',
			'f'			=> [
				'uid'	=> $id,
			],
		]));
		echo '" class="print-hide"></div>';
	}

	echo '<div id="transactions" ';
	echo 'data-url="';
	echo $app->path('transactions', array_merge($app['pp_ary'], [
		'inline'	=> '1',
		'f'			=> [
			'uid'	=> $id,
		],
	]));
	echo '" class="print-hide"></div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/*
 * List all users
 */

$view = $app['s_view']['users'];

$v_list = $view === 'list';
$v_tiles = $view === 'tiles';
$v_map = $view === 'map';

$sql_bind = [];
$params = [];

if (!isset($st[$status]))
{
	$app['link']->redirect('users', $app['pp_ary'], []);
}

if (isset($st[$status]['sql_bind']))
{
	$sql_bind[] = $st[$status]['sql_bind'];
}

$params = [
	'status'	=> $status,
];

$ref_geo = [];

if ($v_list)
{
	$type_contact = $app['db']->fetchAll('select id, abbrev, name
		from ' . $app['tschema'] . '.type_contact');

	$columns = [
		'u'		=> [
			'letscode'		=> 'Code',
			'name'			=> 'Naam',
			'fullname'		=> 'Volledige naam',
			'postcode'		=> 'Postcode',
			'accountrole'	=> 'Rol',
			'saldo'			=> 'Saldo',
			'saldo_date'	=> 'Saldo op ',
			'minlimit'		=> 'Min',
			'maxlimit'		=> 'Max',
			'comments'		=> 'Commentaar',
			'hobbies'		=> 'Hobbies/interesses',
		],
	];

	if ($app['pp_admin'])
	{
		$columns['u'] += [
			'admincomment'	=> 'Admin commentaar',
			'cron_saldo'	=> 'Periodieke Overzichts E-mail',
			'cdate'			=> 'Gecreëerd',
			'mdate'			=> 'Aangepast',
			'adate'			=> 'Geactiveerd',
			'lastlogin'		=> 'Laatst ingelogd',
		];
	}

	foreach ($type_contact as $tc)
	{
		$columns['c'][$tc['abbrev']] = $tc['name'];
	}

	if (!$app['s_elas_guest'])
	{
		$columns['d'] = [
			'distance'	=> 'Afstand',
		];
	}

	$columns['m'] = [
		'wants'		=> 'Vraag',
		'offers'	=> 'Aanbod',
		'total'		=> 'Vraag en aanbod',
	];

	$message_type_filter = [
		'wants'		=> ['want' => 'on'],
		'offers'	=> ['offer' => 'on'],
		'total'		=> '',
	];

	$columns['a'] = [
		'trans'		=> [
			'in'	=> 'Transacties in',
			'out'	=> 'Transacties uit',
			'total'	=> 'Transacties totaal',
		],
		'amount'	=> [
			'in'	=> $app['config']->get('currency', $app['tschema']) . ' in',
			'out'	=> $app['config']->get('currency', $app['tschema']) . ' uit',
			'total'	=> $app['config']->get('currency', $app['tschema']) . ' totaal',
		],
	];

	$columns['p'] = [
		'c'	=> [
			'adr_split'	=> '.',
		],
		'a'	=> [
			'days'	=> '.',
			'code'	=> '.',
		],
		'u'	=> [
			'saldo_date'	=> '.',
		],
	];

	$session_users_columns_key = 'users_columns_';
	$session_users_columns_key .= $app['pp_role'];
	$session_users_columns_key .= $app['s_elas_guest'] ? '_elas' : '';

	if (isset($_GET['sh']))
	{
		$show_columns = $_GET['sh'] ?? [];

		$show_columns = array_intersect_key_recursive($show_columns, $columns);

		$app['session']->set($session_users_columns_key, $show_columns);
	}
	else
	{
		if ($app['pp_admin'] || $app['s_guest'])
		{
			$preset_columns = [
				'u'	=> [
					'letscode'	=> 1,
					'name'		=> 1,
					'postcode'	=> 1,
					'saldo'		=> 1,
				],
			];
		}
		else
		{
			$preset_columns = [
				'u' => [
					'letscode'	=> 1,
					'name'		=> 1,
					'postcode'	=> 1,
					'saldo'		=> 1,
				],
				'c'	=> [
					'gsm'	=> 1,
					'tel'	=> 1,
					'adr'	=> 1,
				],
				'd'	=> [
					'distance'	=> 1,
				],
			];
		}

		if ($app['s_elas_guest'])
		{
			unset($columns['d']['distance']);
		}

		$show_columns = $app['session']->get($session_users_columns_key) ?? $preset_columns;
	}

	$adr_split = $show_columns['p']['c']['adr_split'] ?? '';
	$activity_days = $show_columns['p']['a']['days'] ?? 365;
	$activity_days = $activity_days < 1 ? 365 : $activity_days;
	$activity_filter_code = $show_columns['p']['a']['code'] ?? '';
	$saldo_date = $show_columns['p']['u']['saldo_date'] ?? '';
	$saldo_date = trim($saldo_date);

	$users = $app['db']->fetchAll('select u.*
		from ' . $app['tschema'] . '.users u
		where ' . $st[$status]['sql'] . '
		order by u.letscode asc', $sql_bind);

// hack eLAS compatibility (in eLAND limits can be null)

	if (isset($show_columns['u']['minlimit']) || isset($show_columns['u']['maxlimit']))
	{
		foreach ($users as &$user)
		{
			$user['minlimit'] = $user['minlimit'] === -999999999 ? '' : $user['minlimit'];
			$user['maxlimit'] = $user['maxlimit'] === 999999999 ? '' : $user['maxlimit'];
		}
	}

	if (isset($show_columns['u']['fullname']))
	{
		foreach ($users as &$user)
		{
			$user['fullname_access'] = $app['xdb']->get(
				'user_fullname_access',
				$user['id'],
				$app['tschema']
			)['data']['fullname_access'] ?? 'admin';

			error_log($user['fullname_access']);
		}
	}

	if (isset($show_columns['u']['saldo_date']))
	{
		if ($saldo_date)
		{
			$saldo_date_rev = $app['date_format']->reverse($saldo_date, 'min', $app['tschema']);
		}

		if ($saldo_date_rev === '' || $saldo_date == '')
		{
			$saldo_date = $app['date_format']->get('', 'day', $app['tschema']);

			array_walk($users, function(&$user, $user_id){
				$user['saldo_date'] = $user['saldo'];
			});
		}
		else
		{
			$in = $out = [];
			$datetime = new \DateTime($saldo_date_rev);

			$rs = $app['db']->prepare('select id_to, sum(amount)
				from ' . $app['tschema'] . '.transactions
				where date <= ?
				group by id_to');

			$rs->bindValue(1, $datetime, 'datetime');

			$rs->execute();

			while($row = $rs->fetch())
			{
				$in[$row['id_to']] = $row['sum'];
			}

			$rs = $app['db']->prepare('select id_from, sum(amount)
				from ' . $app['tschema'] . '.transactions
				where date <= ?
				group by id_from');
			$rs->bindValue(1, $datetime, 'datetime');

			$rs->execute();

			while($row = $rs->fetch())
			{
				$out[$row['id_from']] = $row['sum'];
			}

			array_walk($users, function(&$user) use ($out, $in){
				$user['saldo_date'] = 0;
				$user['saldo_date'] += $in[$user['id']] ?? 0;
				$user['saldo_date'] -= $out[$user['id']] ?? 0;
			});
		}
	}

	if (isset($show_columns['c']) || (isset($show_columns['d']) && !$app['s_master']))
	{
		$c_ary = $app['db']->fetchAll('select tc.abbrev,
				c.id_user, c.value, c.flag_public
			from ' . $app['tschema'] . '.contact c, ' .
				$app['tschema'] . '.type_contact tc, ' .
				$app['tschema'] . '.users u
			where tc.id = c.id_type_contact ' .
				(isset($show_columns['c']) ? '' : 'and tc.abbrev = \'adr\' ') .
				'and c.id_user = u.id
				and ' . $st[$status]['sql'], $sql_bind);

		$contacts = [];

		foreach ($c_ary as $c)
		{
			$contacts[$c['id_user']][$c['abbrev']][] = [$c['value'], $c['flag_public']];
		}
	}

	if (isset($show_columns['d']) && !$app['s_master'])
	{
		if (($app['s_guest'] && $app['s_schema'] && !$app['s_elas_guest'])
			|| !isset($contacts[$app['s_id']]['adr']))
		{
			$my_adr = $app['db']->fetchColumn('select c.value
				from ' . $app['s_schema'] . '.contact c, ' .
					$app['s_schema'] . '.type_contact tc
				where c.id_user = ?
					and c.id_type_contact = tc.id
					and tc.abbrev = \'adr\'', [$app['s_id']]);
		}
		else if (!$app['s_guest'])
		{
			$my_adr = trim($contacts[$app['s_id']]['adr'][0][0]);
		}

		if (isset($my_adr))
		{
			$ref_geo = $app['cache']->get('geo_' . $my_adr);
		}
	}

	if (isset($show_columns['m']))
	{
		$msgs_count = [];

		if (isset($show_columns['m']['offers']))
		{
			$ary = $app['db']->fetchAll('select count(m.id), m.id_user
				from ' . $app['tschema'] . '.messages m, ' .
					$app['tschema'] . '.users u
				where msg_type = 1
					and m.id_user = u.id
					and ' . $st[$status]['sql'] . '
				group by m.id_user', $sql_bind);

			foreach ($ary as $a)
			{
				$msgs_count[$a['id_user']]['offers'] = $a['count'];
			}
		}

		if (isset($show_columns['m']['wants']))
		{
			$ary = $app['db']->fetchAll('select count(m.id), m.id_user
				from ' . $app['tschema'] . '.messages m, ' .
					$app['tschema'] . '.users u
				where msg_type = 0
					and m.id_user = u.id
					and ' . $st[$status]['sql'] . '
				group by m.id_user', $sql_bind);

			foreach ($ary as $a)
			{
				$msgs_count[$a['id_user']]['wants'] = $a['count'];
			}
		}

		if (isset($show_columns['m']['total']))
		{
			$ary = $app['db']->fetchAll('select count(m.id), m.id_user
				from ' . $app['tschema'] . '.messages m, ' .
					$app['tschema'] . '.users u
				where m.id_user = u.id
					and ' . $st[$status]['sql'] . '
				group by m.id_user', $sql_bind);

			foreach ($ary as $a)
			{
				$msgs_count[$a['id_user']]['total'] = $a['count'];
			}
		}
	}

	if (isset($show_columns['a']))
	{
		$activity = [];

		$ts = gmdate('Y-m-d H:i:s', time() - ($activity_days * 86400));
		$sql_bind = [$ts];

		$activity_filter_code = trim($activity_filter_code);

		if ($activity_filter_code)
		{
			[$code_only_activity_filter_code] = explode(' ', $activity_filter_code);
			$and = ' and u.letscode <> ? ';
			$sql_bind[] = trim($code_only_activity_filter_code);
		}
		else
		{
			$and = ' and 1 = 1 ';
		}

		$in_ary = $app['db']->fetchAll('select sum(t.amount),
				count(t.id), t.id_to
			from ' . $app['tschema'] . '.transactions t, ' .
				$app['tschema'] . '.users u
			where t.id_from = u.id
				and t.cdate > ?' . $and . '
			group by t.id_to', $sql_bind);

		$out_ary = $app['db']->fetchAll('select sum(t.amount),
				count(t.id), t.id_from
			from ' . $app['tschema'] . '.transactions t, ' .
				$app['tschema'] . '.users u
			where t.id_to = u.id
				and t.cdate > ?' . $and . '
			group by t.id_from', $sql_bind);

		foreach ($in_ary as $in)
		{
			if (!isset($activity[$in['id_to']]))
			{
				$activity[$in['id_to']] = [
					'trans'	=> ['total' => 0],
					'amount' => ['total' => 0],
				];
			}

			$activity[$in['id_to']]['trans']['in'] = $in['count'];
			$activity[$in['id_to']]['amount']['in'] = $in['sum'];
			$activity[$in['id_to']]['trans']['total'] += $in['count'];
			$activity[$in['id_to']]['amount']['total'] += $in['sum'];
		}

		foreach ($out_ary as $out)
		{
			if (!isset($activity[$out['id_from']]))
			{
				$activity[$out['id_from']] = [
					'trans'	=> ['total' => 0],
					'amount' => ['total' => 0],
				];
			}

			$activity[$out['id_from']]['trans']['out'] = $out['count'];
			$activity[$out['id_from']]['amount']['out'] = $out['sum'];
			$activity[$out['id_from']]['trans']['total'] += $out['count'];
			$activity[$out['id_from']]['amount']['total'] += $out['sum'];
		}
	}
}
else
{
	$users = $app['db']->fetchAll('select u.*
		from ' . $app['tschema'] . '.users u
		where ' . $st[$status]['sql'] . '
		order by u.letscode asc', $sql_bind);

	if ($v_map)
	{
		$c_ary = $app['db']->fetchAll('select tc.abbrev,
			c.id_user, c.value, c.flag_public, c.id
			from ' . $app['tschema'] . '.contact c, ' .
				$app['tschema'] . '.type_contact tc
			where tc.id = c.id_type_contact
				and tc.abbrev in (\'mail\', \'tel\', \'gsm\', \'adr\')');

		$contacts = [];

		foreach ($c_ary as $c)
		{
			$contacts[$c['id_user']][$c['abbrev']][] = [
				$c['value'],
				$c['flag_public'],
				$c['id'],
			];
		}

		if (!$app['s_master'])
		{
			if ($app['s_guest'] && $app['s_schema'] && !$app['s_elas_guest'])
			{
				$my_adr = $app['db']->fetchColumn('select c.value
					from ' . $app['s_schema'] . '.contact c, ' . $app['s_schema'] . '.type_contact tc
					where c.id_user = ?
						and c.id_type_contact = tc.id
						and tc.abbrev = \'adr\'', [$app['s_id']]);
			}
			else if (!$app['s_guest'])
			{
				$my_adr = trim($contacts[$app['s_id']]['adr'][0][0]);
			}

			if (isset($my_adr))
			{
				$ref_geo = $app['cache']->get('geo_' . $my_adr);
			}
		}
	}
}

if ($app['pp_admin'])
{
	if ($v_list)
	{
		$app['btn_nav']->csv();
	}

	$app['btn_top']->add('users', $app['pp_ary'],
		['add' => 1], 'Gebruiker toevoegen');

	if ($v_list)
	{
		$app['btn_top']->local('#actions', 'Bulk acties', 'envelope-o');
	}

	$app['heading']->add('Gebruikers');
}
else
{
	$app['heading']->add('Leden');
}

if ($v_list)
{
	$app['btn_nav']->columns_show();
}

$app['btn_nav']->view('users', $app['pp_ary'],
	array_merge($params, ['view' => 'list']),
	'Lijst', 'align-justify', $v_list);

$app['btn_nav']->view('users', $app['pp_ary'],
	array_merge($params, ['view' => 'tiles']),
	'Tegels met foto\'s', 'th', $v_tiles);

$app['btn_nav']->view('users', $app['pp_ary'],
	array_merge($params, ['view' => 'map']),
	'Kaart', 'map-marker', $v_map);

$app['heading']->fa('users');

if ($v_list)
{
	$app['assets']->add([
		'calc_sum.js',
		'users_distance.js',
		'datepicker',
	]);

	if ($app['pp_admin'])
	{
		$app['assets']->add([
			'summernote',
			'table_sel.js',
			'rich_edit.js',
		]);
	}
}
else if ($v_tiles)
{
	$app['assets']->add([
		'isotope',
		'users_tiles.js',
	]);
}
else if ($v_map)
{
	$app['assets']->add([
		'leaflet',
		'users_map.js',
	]);
}

include __DIR__ . '/include/header.php';

if ($v_map)
{
	$lat_add = $lng_add = 0;
	$data_users = $not_geocoded_ary = $not_present_ary = [];
	$hidden_count = $not_geocoded_count = $not_present_count = 0;

	foreach ($users as $user)
	{
		$adr = $contacts[$user['id']]['adr'][0] ?? false;

		if ($adr)
		{
			if ($adr[1] >= $app['s_access_level'])
			{
				$geo = $app['cache']->get('geo_' . $adr[0]);

				if ($geo)
				{
					$data_users[$user['id']] = [
						'name'		=> $user['name'],
						'letscode'	=> $user['letscode'],
						'lat'		=> $geo['lat'],
						'lng'		=> $geo['lng'],
					];

					$lat_add += $geo['lat'];
					$lng_add += $geo['lng'];

					continue;
				}
				else
				{
					$not_geocoded_count++;
					$not_geocoded_ary[] = [
						'uid'	=> $user['id'],
						'adr'	=> $adr[0],
						'id'	=> $adr[2],
					];
				}
			}
			else
			{
				$hidden_count++;
			}
		}
		else
		{
			$not_present_count++;
			$not_present_ary[] = $user['id'];
		}
	}

	$shown_count = count($data_users);
	$not_shown_count = $hidden_count + $not_present_count + $not_geocoded_count;
	$total_count = $shown_count + $not_shown_count;

	if (!count($ref_geo) && $shown_count)
	{
		$ref_geo['lat'] = $lat_add / $shown_count;
		$ref_geo['lng'] = $lng_add / $shown_count;
	}

	$data_users = json_encode($data_users);

	echo '<div class="row">';
	echo '<div class="col-md-12">';
	echo '<div class="users_map" id="map" ';
	echo 'data-users="';
	echo htmlspecialchars($data_users);
	echo '" ';
	echo 'data-lat="';
	echo $ref_geo['lat'] ?? '';
	echo '" ';
	echo 'data-lng="';
	echo $ref_geo['lng'] ?? '';
	echo '" ';
	echo 'data-token="';
	echo $app['mapbox_token'];
	echo '" ';
	echo 'data-session-param="';
	echo '"></div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';
	echo '<p>';

	echo 'In dit kaartje wordt van elke gebruiker slechts het eerste ';
	echo 'adres in de contacten getoond. ';

	echo '</p>';

	if ($not_shown_count > 0)
	{
		echo '<p>';
		echo 'Van in totaal ' . $total_count;
		echo ' gebruikers worden ';
		echo $not_shown_count;
		echo ' adressen niet getoond wegens: ';
		echo '<ul>';

		if ($hidden_count)
		{
			echo '<li>';
			echo '<strong>';
			echo $hidden_count;
			echo '</strong> ';
			echo 'verborgen adres';
			echo '</li>';
		}

		if ($not_present_count)
		{
			echo '<li>';
			echo '<strong>';
			echo $not_present_count;
			echo '</strong> ';
			echo 'geen adres gekend';
			echo '</li>';
		}

		if ($not_geocoded_count)
		{
			echo '<li>';
			echo '<strong>';
			echo $not_geocoded_count;
			echo '</strong> ';
			echo 'coordinaten niet gekend.';
			echo '</li>';
		}

		echo '</ul>';
		echo '</p>';

		if ($not_geocoded_count)
		{
			echo '<h4>';
			echo 'Coördinaten niet gekend';
			echo '</h4>';
			echo '<p>';
			echo 'Wanneer een adres aangepast is of net toegevoegd, ';
			echo 'duurt het enige tijd eer de coordinaten zijn ';
			echo 'opgezocht door de software ';
			echo '(maximum één dag). ';
			echo 'Het kan ook dat bepaalde adressen niet vertaalbaar zijn door ';
			echo 'de "geocoding service".';
			echo '</p>';

			if ($app['pp_admin'])
			{
				echo '<p>';
				echo 'Hieronder de adressen die nog niet ';
				echo 'vertaald zijn in coördinaten: ';
				echo '<ul>';

				foreach($not_geocoded_ary as $not_geocoded)
				{
					echo '<li>';

					echo $app['link']->link_no_attr('contacts', $app['pp_ary'],
						['edit' => $not_geocoded['id'], 'uid' => $not_geocoded['uid']],
						$not_geocoded['adr']);

					echo ' gebruiker: ';
					echo $app['account']->link($not_geocoded['uid'], $app['pp_ary']);
					echo '</li>';
				}

				echo '</ul>';
				echo '</p>';
			}
		}

		if ($app['pp_admin'] && $not_present_count)
		{
			echo '<h4>';
			echo 'Gebruikers zonder adres';
			echo '</h4>';

			echo '<p>';
			echo '<ul>';

			foreach ($not_present_ary as $not_present_addres_uid)
			{
				echo '<li>';
				echo $app['account']->link($not_present_addres_uid, $app['pp_ary']);
				echo '</li>';
			}

			echo '</ul>';
			echo '</p>';
		}
	}

	echo '</div>';
	echo '</div>';
}

if ($v_list || $v_tiles)
{
	echo '<form method="get" action="';
	echo $app->path('users', $params);
	echo '">';

	foreach ($params as $k => $v)
	{
		echo '<input type="hidden" name="' . $k . '" value="' . $v . '">';
	}
}

if ($v_list)
{
	echo '<div class="panel panel-info collapse" ';
	echo 'id="columns_show">';
	echo '<div class="panel-heading">';
	echo '<h2>Weergave kolommen</h2>';

	echo '<div class="row">';

	foreach ($columns as $group => $ary)
	{
		if ($group === 'p')
		{
			continue;
		}

		if ($group === 'm' || $group === 'c')
		{
			echo '</div>';
		}

		if ($group === 'u' || $group === 'c' || $group === 'm')
		{
			echo '<div class="col-md-4">';
		}

		if ($group === 'c')
		{
			echo '<h3>Contacten</h3>';
		}
		else if ($group === 'd')
		{
			echo '<h3>Afstand</h3>';
			echo '<p>Tussen eigen adres en adres van gebruiiker. ';
			echo 'De kolom wordt niet getoond wanneer het eigen adres ';
			echo 'niet ingesteld is.</p>';
		}
		else if ($group === 'a')
		{
			echo '<h3>Transacties/activiteit</h3>';

			echo '<div class="form-group">';
			echo '<label for="p_activity_days" ';
			echo 'class="control-label">';
			echo 'In periode';
			echo '</label>';
			echo '<div class="input-group">';
			echo '<span class="input-group-addon">';
			echo 'dagen';
			echo '</span>';
			echo '<input type="number" ';
			echo 'id="p_activity_days" ';
			echo 'name="sh[p][a][days]" ';
			echo 'value="';
			echo $activity_days;
			echo '" ';
			echo 'size="4" min="1" class="form-control">';
			echo '</div>';
			echo '</div>';

			$app['typeahead']->ini($app['pp_ary'])
				->add('accounts', ['status' => 'active']);

			if (!$app['s_guest'])
			{
				$app['typeahead']->add('accounts', ['status' => 'extern']);
			}

			if ($app['pp_admin'])
			{
				$app['typeahead']->add('accounts', ['status' => 'inactive'])
					->add('accounts', ['status' => 'ip'])
					->add('accounts', ['status' => 'im']);
			}

			echo '<div class="form-group">';
			echo '<label for="p_activity_filter_letscode" ';
			echo 'class="control-label">';
			echo 'Exclusief tegenpartij';
			echo '</label>';
			echo '<div class="input-group">';
			echo '<span class="input-group-addon">';
			echo '<i class="fa fa-user"></i>';
			echo '</span>';
			echo '<input type="text" ';
			echo 'name="sh[p][a][code]" ';
			echo 'id="p_activity_filter_code" ';
			echo 'value="';
			echo $activity_filter_code;
			echo '" ';
			echo 'placeholder="Account Code" ';
			echo 'class="form-control" ';
			echo 'data-typeahead="';

			echo $app['typeahead']->str([
				'filter'		=> 'accounts',
				'newuserdays'	=> $app['config']->get('newuserdays', $app['tschema']),
			]);

			echo '">';
			echo '</div>';
			echo '</div>';

			foreach ($ary as $a_type => $a_ary)
			{
				foreach($a_ary as $key => $lbl)
				{
					$checkbox_id = 'id_' . $group . '_' . $a_type . '_' . $key;

					echo '<div class="checkbox">';
					echo '<label for="';
					echo $checkbox_id;
					echo '">';
					echo '<input type="checkbox" ';
					echo 'id="';
					echo $checkbox_id;
					echo '" ';
					echo 'name="sh[' . $group . '][' . $a_type . '][' . $key . ']" ';
					echo 'value="1"';
					echo isset($show_columns[$group][$a_type][$key]) ? ' checked="checked"' : '';
					echo '> ' . $lbl;
					echo '</label>';
					echo '</div>';
				}
			}

			echo '</div>';

			continue;
		}
		else if ($group === 'm')
		{
			echo '<h3>Vraag en aanbod</h3>';
		}

		foreach ($ary as $key => $lbl)
		{
			$checkbox_id = 'id_' . $group . '_' . $key;

			echo '<div class="checkbox">';
			echo '<label for="';
			echo $checkbox_id;
			echo '">';
			echo '<input type="checkbox" name="sh[';
			echo $group . '][' . $key . ']" ';
			echo 'id="';
			echo $checkbox_id;
			echo '" ';
			echo 'value="1"';
			echo isset($show_columns[$group][$key]) ? ' checked="checked"' : '';
			echo '> ';
			echo $lbl;

			if ($key === 'adr')
			{
				echo ', split door teken: ';
				echo '<input type="text" ';
				echo 'name="sh[p][c][adr_split]" ';
				echo 'size="1" value="';
				echo $adr_split;
				echo '">';
			}

			if ($key === 'saldo_date')
			{
				echo '<div class="input-group">';
				echo '<span class="input-group-addon">';
				echo '<i class="fa fa-calendar"></i>';
				echo '</span>';
				echo '<input type="text" ';
				echo 'class="form-control" ';
				echo 'name="sh[p][u][saldo_date]" ';
				echo 'data-provide="datepicker" ';
				echo 'data-date-format="';
				echo $app['date_format']->datepicker_format($app['tschema']);
				echo '" ';
				echo 'data-date-language="nl" ';
				echo 'data-date-today-highlight="true" ';
				echo 'data-date-autoclose="true" ';
				echo 'data-date-enable-on-readonly="false" ';
				echo 'data-date-end-date="0d" ';
				echo 'data-date-orientation="bottom" ';
				echo 'placeholder="';
				echo $app['date_format']->datepicker_placeholder($app['tschema']);
				echo '" ';
				echo 'value="';
				echo $saldo_date;
				echo '">';
				echo '</div>';

				$columns['u']['saldo_date'] = 'Saldo op ' . $saldo_date;
			}

			echo '</label>';
			echo '</div>';
		}
	}

	echo '</div>';
	echo '<div class="row">';
	echo '<div class="col-md-12">';
	echo '<input type="submit" name="show" ';
	echo 'class="btn btn-default" ';
	echo 'value="Pas weergave kolommen aan">';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
}

if ($v_list || $v_tiles)
{
	echo '<br>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<div class="row">';
	echo '<div class="col-xs-12">';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-search"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="q" name="q" value="' . $q . '" ';
	echo 'placeholder="Zoeken">';
	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '</div>';

	echo '</form>';

	echo '<div class="pull-right hidden-xs hidden-sm print-hide">';
	echo 'Totaal: <span id="total"></span>';
	echo '</div>';

	echo '<ul class="nav nav-tabs" id="nav-tabs">';

	$nav_params = $params;

	foreach ($st as $k => $tab)
	{
		$nav_params['status'] = $k;

		echo '<li';
		echo $status === $k ? ' class="active"' : '';
		echo '>';

		$class_ary = isset($tab['cl']) ? ['class' => 'bg-' . $tab['cl']] : [];

		echo $app['link']->link('users', $app['pp_ary'],
			$nav_params, $tab['lbl'], $class_ary);

		echo '</li>';
	}

	echo '</ul>';
}

if ($v_list)
{
	echo '<div class="panel panel-success printview">';
	echo '<div class="table-responsive">';

	echo '<table class="table table-bordered table-striped table-hover footable csv" ';
	echo 'data-filtering="true" data-filter-delay="0" ';
	echo 'data-filter="#q" data-filter-min="1" data-cascade="true" ';
	echo 'data-empty="Er zijn geen ';
	echo $app['pp_admin'] ? 'gebruikers' : 'leden';
	echo ' volgens de selectiecriteria" ';
	echo 'data-sorting="true" ';
	echo 'data-filter-placeholder="Zoeken" ';
	echo 'data-filter-position="left"';

	if (count($ref_geo))
	{
		echo ' data-lat="' . $ref_geo['lat'] . '" ';
		echo 'data-lng="' . $ref_geo['lng'] . '"';
	}

	echo '>';
	echo '<thead>';

	echo '<tr>';

	$numeric_keys = [
		'saldo'			=> true,
		'saldo_date'	=> true,
	];

	$date_keys = [
		'cdate'			=> true,
		'mdate'			=> true,
		'adate'			=> true,
		'lastlogin'		=> true,
	];

	$link_user_keys = [
		'letscode'		=> true,
		'name'			=> true,
	];

	foreach ($show_columns as $group => $ary)
	{
		if ($group === 'p')
		{
			continue;
		}
		else if ($group === 'a')
		{
			foreach ($ary as $a_key => $a_ary)
			{
				foreach ($a_ary as $key => $one)
				{
					echo '<th data-type="numeric">';
					echo $columns[$group][$a_key][$key];
					echo '</th>';
				}
			}

			continue;
		}
		else if ($group === 'd')
		{
			if (count($ref_geo))
			{
				foreach($ary as $key => $one)
				{
					echo '<th>';
					echo $columns[$group][$key];
					echo '</th>';
				}
			}

			continue;
		}
		else if ($group === 'c')
		{
			$tpl = '<th data-hide="tablet, phone" data-sort-ignore="true">%1$s</th>';

			foreach ($ary as $key => $one)
			{
				if ($key == 'adr' && $adr_split != '')
				{
					echo sprintf($tpl, 'Adres (1)');
					echo sprintf($tpl, 'Adres (2)');
					continue;
				}

				echo sprintf($tpl, $columns[$group][$key]);
			}

			continue;
		}
		else if ($group === 'u')
		{
			foreach ($ary as $key => $one)
			{
				$data_type =  isset($numeric_keys[$key]) ? ' data-type="numeric"' : '';
				$data_sort_initial = $key === 'letscode' ? ' data-sort-initial="true"' : '';

				echo '<th' . $data_type . $data_sort_initial . '>';
				echo $columns[$group][$key];
				echo '</th>';
			}

			continue;
		}
		else if ($group === 'm')
		{
			foreach ($ary as $key => $one)
			{
				echo '<th data-type="numeric">';
				echo $columns[$group][$key];
				echo '</th>';
			}

			continue;
		}
	}

	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	$checkbox = '<input type="checkbox" name="sel_%1$s" value="1"%2$s>&nbsp;';

	$can_link = $app['pp_admin'];

	foreach($users as $u)
	{
		if (($app['s_user'] || $app['s_guest'])
			&& ($u['status'] === 1 || $u['status'] === 2))
		{
			$can_link = true;
		}

		$id = $u['id'];

		if (isset($u['adate'])
			&& $u['status'] === 1
			&& $app['new_user_treshold'] < strtotime($u['adate'])
		)
		{
			$row_stat = 3;
		}
		else
		{
			$row_stat = $u['status'];
		}

		$first = true;

		echo '<tr';

		if (isset(cnst_status::CLASS_ARY[$row_stat]))
		{
			echo ' class="';
			echo cnst_status::CLASS_ARY[$row_stat];
			echo '"';
		}

		echo ' data-balance="';
		echo $u['saldo'];
		echo '">';

		if (isset($show_columns['u']))
		{
			foreach ($show_columns['u'] as $key => $one)
			{
				echo '<td';
				echo isset($date_keys[$key]) ? ' data-value="' . $u[$key] . '"' : '';
				echo '>';

				echo $app['pp_admin'] && $first ? sprintf($checkbox, $id, isset($selected_users[$id]) ? ' checked="checked"' : '') : '';
				$first = false;

				if (isset($link_user_keys[$key]))
				{
					if ($can_link)
					{
						echo $app['link']->link_no_attr('users', $app['pp_ary'],
							['id' => $u['id']], $u[$key]);
					}
					else
					{
						echo htmlspecialchars($u[$key], ENT_QUOTES);
					}
				}
				else if (isset($date_keys[$key]))
				{
					if ($u[$key])
					{
						echo $app['date_format']->get($u[$key], 'day', $app['tschema']);
					}
					else
					{
						echo '&nbsp;';
					}
				}
				else if ($key === 'fullname')
				{
					if ($app['pp_admin']
						|| $u['fullname_access'] === 'interlets'
						|| ($app['s_user'] && $u['fullname_access'] !== 'admin'))
					{
						if ($can_link)
						{
							echo $app['link']->link_no_attr('users', $app['pp_ary'],
								['id' => $u['id']], $u['fullname']);
						}
						else
						{
							echo htmlspecialchars($u['fullname'], ENT_QUOTES);
						}
					}
					else
					{
						echo '<span class="btn btn-default">';
						echo 'verborgen</span>';
					}
				}
				else if ($key === 'accountrole')
				{
					echo cnst_role::LABEL_ARY[$u['accountrole']];
				}
				else
				{
					echo htmlspecialchars($u[$key]);
				}

				echo '</td>';
			}
		}

		if (isset($show_columns['c']))
		{
			foreach ($show_columns['c'] as $key => $one)
			{
				echo '<td>';

				if ($key == 'adr' && $adr_split != '')
				{
					if (!isset($contacts[$id][$key]))
					{
						echo '&nbsp;</td><td>&nbsp;</td>';
						continue;
					}

					[$adr_1, $adr_2] = explode(trim($adr_split), $contacts[$id]['adr'][0][0]);

					echo get_contacts_str([[$adr_1, $contacts[$id]['adr'][0][1]]], 'adr');
					echo '</td><td>';
					echo get_contacts_str([[$adr_2, $contacts[$id]['adr'][0][1]]], 'adr');
				}
				else if (isset($contacts[$id][$key]))
				{
					echo get_contacts_str($contacts[$id][$key], $key);
				}
				else
				{
					echo '&nbsp;';
				}

				echo '</td>';
			}
		}

		if (isset($show_columns['d']) && count($ref_geo))
		{
			echo '<td data-value="5000"';

			$adr_ary = $contacts[$id]['adr'][0] ?? [];

			if (isset($adr_ary[1]))
			{
				if ($adr_ary[1] >= $app['s_access_level'])
				{
					if (count($adr_ary) && $adr_ary[0])
					{
						$geo = $app['cache']->get('geo_' . $adr_ary[0]);

						if ($geo)
						{
							echo ' data-lat="';
							echo $geo['lat'];
							echo '" data-lng="';
							echo $geo['lng'];
							echo '"';
						}
					}

					echo '><i class="fa fa-times"></i>';
				}
				else
				{
					echo '><span class="btn btn-default">verborgen</span>';
				}
			}
			else
			{
				echo '><i class="fa fa-times"></i>';
			}

			echo '</td>';
		}

		if (isset($show_columns['m']))
		{
			foreach($show_columns['m'] as $key => $one)
			{
				echo '<td>';

				if (isset($msgs_count[$id][$key]))
				{
					echo $app['link']->link_no_attr('messages', $app['pp_ary'],
						[
							'f'	=> [
								'uid' 	=> $id,
								'type' 	=> $message_type_filter[$key],
							],
						],
						$msgs_count[$id][$key]);
				}

				echo '</td>';
			}
		}

		if (isset($show_columns['a']))
		{
			$from_date = $app['date_format']->get_from_unix(time() - ($activity_days * 86400), 'day', $app['tschema']);

			foreach($show_columns['a'] as $a_key => $a_ary)
			{
				foreach ($a_ary as $key => $one)
				{
					echo '<td>';

					if (isset($activity[$id][$a_key][$key]))
					{
						if (isset($code_only_activity_filter_code))
						{
							echo $activity[$id][$a_key][$key];
						}
						else
						{
							echo $app['link']->link_no_attr('transactions', $app['pp_ary'],
								[
									'f' => [
										'fcode'	=> $key === 'in' ? '' : $u['letscode'],
										'tcode'	=> $key === 'out' ? '' : $u['letscode'],
										'andor'	=> $key === 'total' ? 'or' : 'and',
										'fdate' => $from_date,
									],
								],
								$activity[$id][$a_key][$key]);
						}
					}

					echo '</td>';
				}
			}
		}

		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div></div>';

	echo '<div class="row"><div class="col-md-12">';
	echo '<p><span class="pull-right">Totaal saldo: <span id="sum"></span> ';
	echo $app['config']->get('currency', $app['tschema']);
	echo '</span></p>';
	echo '</div></div>';

	if ($app['pp_admin'] & isset($show_columns['u']))
	{
		$bulk_mail_cc = $app['request']->isMethod('POST') ? $bulk_mail_cc : true;

		$inp =  '<div class="form-group">';
		$inp .=  '<label for="%5$s" class="control-label">%2$s</label>';
		$inp .= '<div class="input-group">';
		$inp .= '<span class="input-group-addon">';
		$inp .= '<span class="fa fa-%6$s"></span></span>';
		$inp .=  '<input type="%3$s" id="%5$s" name="%1$s" %4$s>';
		$inp .=  '</div>';
		$inp .=  '</div>';

		$checkbox = '<div class="form-group">';
		$checkbox .= '<label for="%5$s" class="control-label">';
		$checkbox .= '<input type="%3$s" id="%5$s" name="%1$s" %4$s>';
		$checkbox .= ' %2$s</label></div>';

		$acc_sel = '<div class="form-group">';
		$acc_sel .= '<label for="%1$s" class="control-label">';
		$acc_sel .= '%2$s</label>';
		$acc_sel .= '<div class="input-group">';
		$acc_sel .= '<span class="input-group-addon">';
		$acc_sel .= '<span class="fa fa-%4$s"></span></span>';
		$acc_sel .= '<select name="%1$s" id="%1$s" class="form-control">';
		$acc_sel .= '%3$s';
		$acc_sel .= '</select>';
		$acc_sel .= '</div>';
		$acc_sel .= '</div>';

		echo '<div class="panel panel-default" id="actions">';
		echo '<div class="panel-heading">';

		echo '<span class="btn btn-default" id="invert_selection">';
		echo 'Selectie omkeren</span>&nbsp;';
		echo '<span class="btn btn-default" id="select_all">';
		echo 'Selecteer alle</span>&nbsp;';
		echo '<span class="btn btn-default" id="deselect_all">';
		echo 'De-selecteer alle</span>';

		echo '</div>';
		echo '</div>';

		echo '<h3>Bulk acties met geselecteerde gebruikers</h3>';
		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';

		echo '<ul class="nav nav-tabs" role="tablist">';

		echo '<li class="active">';
		echo '<a href="#mail_tab" data-toggle="tab">Mail</a></li>';
		echo '<li class="dropdown">';

		echo '<a class="dropdown-toggle" data-toggle="dropdown" href="#">Veld aanpassen';
		echo '<span class="caret"></span></a>';
		echo '<ul class="dropdown-menu">';

		foreach ($edit_fields_tabs as $k => $t)
		{
			echo '<li>';
			echo '<a href="#' . $k . '_tab" data-toggle="tab">';
			echo $t['lbl'];
			echo '</a></li>';
		}

		echo '</ul>';
		echo '</li>';
		echo '</ul>';

		echo '<div class="tab-content">';

		echo '<div role="tabpanel" class="tab-pane active" id="mail_tab">';
		echo '<h3>E-Mail verzenden naar geselecteerde gebruikers</h3>';

		echo '<form method="post">';

		echo '<div class="form-group">';
		echo '<input type="text" class="form-control" id="bulk_mail_subject" name="bulk_mail_subject" ';
		echo 'placeholder="Onderwerp" ';
		echo 'value="';
		echo $bulk_mail_subject ?? '';
		echo '" required>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<textarea name="bulk_mail_content" ';
		echo 'class="form-control rich-edit" ';
		echo 'id="bulk_mail_content" rows="8" ';
		echo 'data-template-vars="';
		echo implode(',', array_keys($map_template_vars));
		echo '" ';
		echo 'required>';
		echo $bulk_mail_content ?? '';
		echo '</textarea>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="bulk_mail_cc" class="control-label">';
		echo '<input type="checkbox" name="bulk_mail_cc" ';
		echo 'id="bulk_mail_cc"';
		echo $bulk_mail_cc ? ' checked="checked"' : '';
		echo ' value="1" > ';
		echo 'Stuur een kopie met verzendinfo naar mijzelf';
		echo '</label>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="verify_mail" class="control-label">';
		echo '<input type="checkbox" name="verify_mail" ';
		echo 'id="verify_mail" ';
		echo 'value="1" required> ';
		echo 'Ik heb mijn bericht nagelezen en nagekeken dat de juiste gebruikers geselecteerd zijn.';
		echo '</label>';
		echo '</div>';

		echo '<input type="submit" value="Zend test E-mail naar jezelf" name="bulk_mail_test" class="btn btn-default">&nbsp;';
		echo '<input type="submit" value="Verzend" name="bulk_mail_submit" class="btn btn-default">';

		echo $app['form_token']->get_hidden_input();
		echo '</form>';
		echo '</div>';

		foreach($edit_fields_tabs as $k => $t)
		{
			echo '<div role="tabpanel" class="tab-pane" id="';
			echo $k;
			echo '_tab"';
			echo isset($t['item_access']) ? ' data-access-control="true"' : '';
			echo '>';
			echo '<h3>Veld aanpassen: ' . $t['lbl'] . '</h3>';

			echo '<form method="post">';

			if (isset($t['options']))
			{
				$options = $t['options'];
				echo sprintf($acc_sel,
					$k,
					$t['lbl'],
					$app['select']->get_options($options, 0),
					$t['fa']);
			}
			else if (isset($t['type']) && $t['type'] == 'checkbox')
			{
				echo sprintf($checkbox, $k, $t['lbl'], $t['type'], 'value="1"', $k);
			}
			else if (isset($t['item_access']))
			{
				echo $app['item_access']->get_radio_buttons('access');
			}
			else
			{
				echo sprintf($inp, $k, $t['lbl'], $t['type'], 'class="form-control"', $k, $t['fa']);
			}

			echo '<div class="form-group">';
			echo '<label for="verify_' . $k . '" class="control-label">';
			echo '<input type="checkbox" name="verify_' . $k . '" ';
			echo 'id="verify_' . $k . '" ';
			echo 'value="1" required> ';
			echo 'Ik heb nagekeken dat de juiste gebruikers geselecteerd zijn en veld en ingevulde waarde nagekeken.';
			echo '</label>';
			echo '</div>';

			echo '<input type="hidden" value="' . $k . '" name="bulk_field">';
			echo '<input type="submit" value="Veld aanpassen" name="' . $k . '_bulk_submit" class="btn btn-primary">';
			echo $app['form_token']->get_hidden_input();
			echo '</form>';

			echo '</div>';
		}

		echo '<div class="clearfix"></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
}
else if ($v_tiles)
{
	echo '<p>';
	echo '<span class="btn-group sort-by" role="group">';
	echo '<button class="btn btn-default active" data-sort-by="letscode">';
	echo 'Account Code ';
	echo '<i class="fa fa-sort-asc"></i></button>';
	echo '<button class="btn btn-default" data-sort-by="name">';
	echo 'Naam ';
	echo '<i class="fa fa-sort"></i></button>';
	echo '<button class="btn btn-default" data-sort-by="postcode">';
	echo 'Postcode ';
	echo '<i class="fa fa-sort"></i></button>';
	echo '</span>';
	echo '</p>';

	echo '<div class="row tiles">';

	foreach ($users as $u)
	{
		if (isset($u['adate'])
			&& $u['status'] === 1
			&& $app['new_user_treshold'] < strtotime($u['adate'])
		)
		{
			$row_stat = 3;
		}
		else
		{
			$row_stat = $u['status'];
		}

		$url = $app['link']->context_path('users', $app['pp_ary'],
			['id' => $u['id'], 'link' => $status]);

		echo '<div class="col-xs-4 col-md-3 col-lg-2 tile">';
		echo '<div';

		if (isset(cnst_status::CLASS_ARY[$row_stat]))
		{
			echo ' class="bg-';
			echo cnst_status::CLASS_ARY[$row_stat];
			echo '"';
		}

		echo '>';
		echo '<div class="thumbnail text-center">';
		echo '<a href="' . $url . '">';

		if (isset($u['PictureFile']) && $u['PictureFile'] != '')
		{
			echo '<img src="';
			echo $app['s3_url'] . $u['PictureFile'];
			echo '" class="img-rounded">';
		}
		else
		{
			echo '<div><i class="fa fa-user fa-5x text-muted"></i></div>';
		}
		echo '</a>';

		echo '<div class="caption">';

		echo '<a href="' . $url . '">';
		echo '<span class="letscode">' . $u['letscode'] . '</span> ';
		echo '<span class="name">' . $u['name'] . '</span>';
		echo '</a>';
		echo '<br><span class="postcode">' . $u['postcode'] . '</span>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	echo '</div>';
}

include __DIR__ . '/include/footer.php';

function get_contacts_str(array $contacts, string $abbrev):string
{
	global $app;

	$ret = '';

	if (count($contacts))
	{
		end($contacts);
		$end = key($contacts);

		$tpl = '%1$s';

		if ($abbrev === 'mail')
		{
			$tpl = '<a href="mailto:%1$s">%1$s</a>';
		}
		else if ($abbrev === 'web')
		{
			$tpl = '<a href="%1$s">%1$s</a>';
		}

		foreach ($contacts as $key => $contact)
		{
			if ($contact[1] >= $app['s_access_level'])
			{
				$ret .= sprintf($tpl, htmlspecialchars($contact[0], ENT_QUOTES));

				if ($key === $end)
				{
					break;
				}

				$ret .= ',<br>';

				continue;
			}

			$ret .= '<span class="btn btn-default">';
			$ret .= 'verborgen</span>';
			$ret .= '<br>';
		}
	}
	else
	{
		$ret .= '&nbsp;';
	}

	return $ret;
}

function get_dd(string $str):string
{
	$out =  '<dd>';
	$out .=  $str ? htmlspecialchars($str, ENT_QUOTES) : '<span class="fa fa-times"></span>';
	$out .=  '</dd>';
	return $out;
}

function send_activation_mail_admin(
	int $user_id
):void
{
	global $app;

	$app['queue.mail']->queue([
		'schema'	=> $app['tschema'],
		'to' 		=> $app['mail_addr_system']->get_admin($app['tschema']),
		'template'	=> 'account_activation/admin',
		'vars'		=> [
			'user_id'		=> $user_id,
			'user_email'	=> $app['mail_addr_user']->get($user_id, $app['tschema']),
		],
	], 5000);
}

function send_activation_mail_user(int $user_id, string $password):void
{
	global $app;

	$app['queue.mail']->queue([
		'schema'	=> $app['tschema'],
		'to' 		=> $app['mail_addr_user']->get($user_id, $app['tschema']),
		'reply_to' 	=> $app['mail_addr_system']->get_support($app['tschema']),
		'template'	=> 'account_activation/user',
		'vars'		=> [
			'user_id'	=> $user_id,
			'password'	=> $password,
		],
	], 5100);
}

function delete_thumbprint(string $status):void
{
	global $app;

	$app['typeahead']->delete_thumbprint('accounts', $app['pp_ary'], [
		'status'	=> $status,
	]);

	if ($status !== 'active')
	{
		return;
	}

	foreach ($app['intersystems']->get_eland($app['tschema']) as $remote_schema => $h)
	{
		$app['typeahead']->delete_thumbprint('eland_intersystem_accounts',
			$app['pp_ary'], [
			'remote_schema'	=> $remote_schema,
		]);
	}
}
