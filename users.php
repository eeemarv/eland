<?php
$rootpath = './';
require_once $rootpath . 'includes/inc_pagination.php';

$q = (isset($_GET['q'])) ? $_GET['q'] : '';
$status = (isset($_GET['status'])) ? $_GET['status'] : false;

$id = (isset($_GET['id'])) ? $_GET['id'] : false;
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$add = (isset($_GET['add'])) ? $_GET['add'] : false;
$pw = (isset($_GET['pw'])) ? $_GET['pw'] : false;
$img = (isset($_GET['img'])) ? true : false;
$img_del = (isset($_GET['img_del'])) ? true : false;
$interlets = (isset($_GET['interlets'])) ? $_GET['interlets'] : false;
$password = (isset($_POST['password'])) ? $_POST['password'] : false;
$submit = (isset($_POST['zend'])) ? true : false;

$user_mail_submit = (isset($_POST['user_mail_submit'])) ? true : false;

$bulk_mail_submit = isset($_POST['bulk_mail_submit']) ? true : false;
$bulk_mail_test = isset($_POST['bulk_mail_test']) ? true : false;
$selected_users = (isset($_POST['sel']) && $_POST['sel'] != '') ? explode(',', $_POST['sel']) : [];

/*
 * general access
 */

$page_access = ($edit || $pw || $img_del || $password || $submit || $img) ? 'user' : 'guest';
$page_access = ($add || $del || $bulk_mail_submit || $bulk_mail_test) ? 'admin' : $page_access;
$allow_guest_post = ($page_access == 'guest' && $user_mail_submit) ? true : false;

require_once $rootpath . 'includes/inc_passwords.php';
require_once $rootpath . 'includes/inc_default.php';

/**
 * selectors for bulk actions
 */
$bulk_field_submit = $bulk_submit = false;

if ($s_admin)
{
	$edit_fields_tabs = [
		'fullname_access'	=> [
			'lbl'				=> 'Zichtbaarheid volledige naam',
			'access_control'	=> true,
		],
		'adr_access'		=> [
			'lbl'		=> 'Zichtbaarheid adres',
			'access_control'	=> true,
		],
		'mail_access'		=> [
			'lbl'		=> 'Zichtbaarheid email adres',
			'access_control'	=> true,
		],
		'tel_access'		=> [
			'lbl'		=> 'Zichtbaarheid telefoonnummer',
			'access_control'	=> true,
		],
		'gsm_access'		=> [
			'lbl'		=> 'Zichtbaarheid gsmnummer',
			'access_control'	=> true,
		],
		'comments'			=> [
			'lbl'		=> 'Commentaar',
			'type'		=> 'text',
			'string'	=> true,
		],
		'accountrole'		=> [
			'lbl'		=> 'Rechten',
			'options'	=> 'role_ary',
			'string'	=> true,
		],
		'status'			=> [
			'lbl'		=> 'Status',
			'options'	=> 'status_ary',
		],
		'admincomment'		=> [
			'lbl'		=> 'Commentaar van de admin',
			'type'		=> 'text',
			'string'	=> true,
		],
		'minlimit'			=> [
			'lbl'		=> 'Minimum limiet saldo',
			'type'		=> 'number',
		],
		'maxlimit'			=> [
			'lbl'		=> 'Maximum limiet saldo',
			'type'		=> 'number',
		],
		'cron_saldo'		=> [
			'lbl'	=> 'Periodieke mail met recent vraag en aanbod (aan/uit)',
			'type'	=> 'checkbox',
		],
	];

	if ($post && !($bulk_mail_test
		|| $bulk_mail_submit
		|| $edit || $add || $id
		|| $img || $img_del || $password
		|| $submit))
	{
		foreach ($edit_fields_tabs as $field => $t)
		{
			if (isset($_POST[$field . '_bulk_submit']))
			{
				$bulk_field_submit = true;
				break;
			}
		}
	}

	$bulk_submit = $bulk_field_submit || $bulk_mail_submit || $bulk_mail_test;
}

/**
 * mail to user
 */

if ($user_mail_submit && $id && $post)
{
	$user_mail_content = $_POST['user_mail_content'];
	$user_mail_cc = $_POST['user_mail_cc'];

	$user = readuser($id);

	if (!$s_admin && !in_array($user['status'], [1, 2]))
	{
		$alert->error('Je hebt geen rechten om een bericht naar een niet-actieve gebruiker te sturen');
		cancel();
	}

	if ($s_master)
	{
		$alert->error('Het master account kan geen berichten versturen.');
		cancel();
	}

	if (!$s_schema)
	{
		$alert->error('Je hebt onvoldoende rechten om een bericht te versturen.');
		cancel();
	}

	$user_me = ($s_group_self) ? '' : readconfigfromdb('systemtag', $s_schema) . '.';
	$user_me .= link_user($session_user, $s_schema, false);
	$user_me .= ($s_group_self) ? '' : ' van interlets groep ' . readconfigfromdb('systemname', $s_schema);

	$my_contacts = $db->fetchAll('select c.value, tc.abbrev
		from ' . $s_schema . '.contact c, ' . $s_schema . '.type_contact tc
		where c.flag_public >= ?
			and c.id_user = ?
			and c.id_type_contact = tc.id', [$access_ary[$user['accountrole']], $s_id]);

	$subject = 'Bericht van ' . $systemname;

	$text = 'Beste ' . $user['name'] . "\r\n\r\n";
	$text .= 'Gebruiker ' . $user_me . " heeft een bericht naar je verstuurd via de webtoepassing\r\n\r\n";
	$text .= '--------------------bericht--------------------' . "\r\n\r\n";
	$text .= $user_mail_content . "\r\n\r\n";
	$text .= '-----------------------------------------------' . "\r\n\r\n";
	$text .= "Om te antwoorden kan je gewoon reply kiezen of de contactgegevens hieronder gebruiken\r\n\r\n";
	$text .= 'Contactgegevens van ' . $user_me . ":\r\n\r\n";

	foreach($my_contacts as $value)
	{
		$text .= '* ' . $value['abbrev'] . "\t" . $value['value'] ."\n";
	}

	if ($user_mail_content)
	{
		if ($user_mail_cc)
		{
			$msg = 'Dit is een kopie van het bericht dat je naar ' . $user['letscode'] . ' ';
			$msg .= $user['name'];
			$msg .= ($s_group_self) ? '' : ' van letsgroep ' . $systemname;
			$msg .= ' verzonden hebt. ';
			$msg .= "\r\n\r\n\r\n";

			mail_q(['to' => $s_schema . '.' . $s_id, 'text' => $msg . $text, 'subject' => $subject . ' (kopie)']);
		}

		if ($user['status'] == 1 || $user['status'] == 2)
		{
			$text .= "\r\n\r\nInloggen op de website: " . $base_url . "\r\n\r\n";
		}

		mail_q(['to' => $id, 'subject' => $subject, 'text' => $text, 'reply_to' => $s_schema . '.' . $s_id]);

		$alert->success('Mail verzonden.');
	}
	else
	{
		$alert->error('Fout: leeg bericht. Mail niet verzonden.');
	}

	cancel($id);
}

/*
 *
 */

if ($post)
{
	$s3 = Aws\S3\S3Client::factory([
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	]);
}

/*
 * upload image
 */

if ($post && $img && $id )
{
	$s_owner = (!$s_guest && $s_group_self && $s_id == $id && $id) ? true : false;

	if (!($s_owner || $s_admin))
	{
		echo json_encode(['error' => 'Je hebt onvoldoende rechten voor deze actie.']);
		exit;
	}

	$user = readuser($id);

	$image = ($_FILES['image']) ?: null;

	if (!$image)
	{
		echo json_encode(['error' => 'Afbeeldingsbestand ontbreekt.']);
		exit;
	}

	$size = $image['size'];
	$tmp_name = $image['tmp_name'];
	$type = $image['type'];

	if ($size > 200 * 1024)
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

	$orientation = $exif['COMPUTED']['Orientation'];

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

	try {

/** to be handled in background process

		if ($user['PictureFile'])
		{
			$s3->deleteObject([
				'Bucket'	=> $s3_img,
				'Key'		=> $user['PictureFile'],
			]);
		}
**/

		$filename = $schema . '_u_' . $id . '_' . sha1(time()) . '.jpg';

		$upload = $s3->upload($s3_img, $filename, fopen($tmpfile, 'rb'), 'public-read', [
			'params'	=> [
				'CacheControl'	=> 'public, max-age=31536000',
				'ContentType'	=> 'image/jpeg',
			],
		]);

		$db->update('users', [
			'"PictureFile"'	=> $filename
		],['id' => $id]);

		log_event('pict', 'User image ' . $filename . ' uploaded. User: ' . $id);

		readuser($id, true);

		unlink($tmp_name);
	}
	catch(Exception $e)
	{
		echo json_encode(['error' => $e->getMessage()]);
		log_event('pict', 'Upload fail : ' . $e->getMessage());
		exit;
	}

	header('Pragma: no-cache');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Content-Disposition: inline; filename="files.json"');
	header('X-Content-Type-Options: nosniff');
	header('Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size');

	header('Vary: Accept');

	echo json_encode(['success' => 1, 'filename' => $filename]);
	exit;
}

/**
 * delete image
 */

if ($img_del && $id)
{
	$s_owner = (!$s_guest && $s_group_self && $s_id == $id && $id) ? true : false;

	if (!($s_owner || $s_admin))
	{
		$alert->error('Je hebt onvoldoende rechten om de foto te verwijderen.');
		cancel($id);
	}

	$user = readuser($id);

	if (!$user)
	{
		$alert->error('De gebruiker bestaat niet.');
		cancel();
	}

	$file = $user['PictureFile'];

	if ($file == '' || !$file)
	{
		$alert->error('De gebruiker heeft geen foto.');
		cancel($id);
	}

	if ($post)
	{

/** to be handled in background process

		$s3->deleteObject([
			'Bucket'	=> $s3_img,
			'Key'		=> $file,
		]);

**/

		$db->update('users', ['"PictureFile"' => ''], ['id' => $id]);
		readuser($id, true);
		$alert->success('Profielfoto verwijderd.');
		cancel($id);
	}

	$h1 = 'Profielfoto ' . (($s_admin) ? 'van ' . link_user($id) . ' ' : '') . 'verwijderen?';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="row">';
	echo '<div class="col-xs-6">';
	echo '<div class="thumbnail">';
	echo '<img src="' . $s3_img_url . $file . '" class="img-rounded">';
	echo '</div>';
	echo '</div>';

	echo '</div>';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo aphp('users', ['id' => $id], 'Annuleren', 'btn btn-default'). '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';

	exit;
}

/**
 * bulk actions
 */

if ($bulk_submit && $post && $s_admin)
{
	if ($bulk_field_submit || $bulk_mail_submit)
	{
		$pw_name_suffix = substr($_POST['form_token'], 0, 5);
		$password = ($bulk_mail_submit) ? 'mail_password_' : $field . '_password_';
		$password = $_POST[$password . $pw_name_suffix];

		$value = $_POST[$field];

		if (!$password)
		{
			$errors[] = 'Vul je paswoord in.';
		}

		$password = hash('sha512', $password);

		$fetched_password = ($s_master) ? getenv('MASTER_PASSWORD') : $session_user['password'];

		if ($password != $fetched_password)
		{
			$errors[] = 'Het paswoord is niet juist.';
		}
	}

	if ($bulk_mail_test || $bulk_mail_submit)
	{
		$bulk_mail_subject = $_POST['bulk_mail_subject'];
		$bulk_mail_content = $_POST['bulk_mail_content'];

		if (!$bulk_mail_subject)
		{
			$errors[] = 'Gelieve een onderwerp in te vullen voor je mail.';
		}

		if (!$bulk_mail_content)
		{
			$errors[] = 'Het mail bericht is leeg.';
		}

		if (!readconfigfromdb('mailenabled'))
		{
			$errors[] = 'Mail functies zijn niet ingeschakeld. Zie instellingen.';
		}

		if ($s_master)
		{
			$errors[] = 'Het master account kan geen berichten verzenden.';
		}
	}

	if (!count($selected_users) && !$bulk_mail_test)
	{
		$errors[] = 'Selecteer ten minste één gebruiker voor deze actie.';
	}

	if ($error_token = get_error_form_token())
	{
		$errors[] = $error_token;
	}

	if (['adr_access' => 1, 'mail_access' => 1, 'tel_access' => 1,
		'gsm_access' => 1, 'fullname_access' => 1][$field])
	{
		$access_value = $access_control->get_post_value();

		if ($access_error = $access_control->get_post_error())
		{
			$errors[] = $access_error;
		}
	}

	if (count($errors))
	{
		$alert->error($errors);
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
if ($s_admin && !count($errors) && $bulk_field_submit && $post)
{
	$users_log = '';

	$rows = $db->executeQuery('select letscode, name, id from users where id in (?)',
			[$user_ids], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($rows as $row)
	{
		$users_log .= ', ' . link_user($row, false, false, true);
	}

	$users_log = ltrim($users_log, ', ');

	if ($field == 'fullname_access')
	{
		$fullname_access_role = $access_control->get_role($access_value);

		foreach ($user_ids as $user_id)
		{
			$exdb->set('user_fullname_access', $user_id, ['fullname_access' => $fullname_access_role]);
			$redis->del($schema . '_user_' . $user_id);
		}

		log_event('bulk', 'Set fullname_access to ' . $fullname_access_role . ' for users ' . $users_log);

		$alert->success('De zichtbaarheid van de volledige naam werd aangepast.');

		cancel();
	}
	else if (['cron_saldo' => 1, 'accountrole' => 1, 'status' => 1, 'comments' => 1,
		'admincomment' => 1, 'minlimit' => 1, 'maxlimit' => 1][$field])
	{
		$type = ($edit_fields_tabs[$field]['string']) ? \PDO::PARAM_STR : \PDO::PARAM_INT;

		$db->executeUpdate('update users set ' . $field . ' = ? where id in (?)',
			[$value, $user_ids],
			[$type, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

		foreach ($user_ids as $user_id)
		{
			$redis->del($schema . '_user_' . $user_id);
		}

		if ($field == 'status')
		{
			invalidate_typeahead_thumbprint('users_active');
			invalidate_typeahead_thumbprint('users_extern');
		}

		log_event('bulk', 'Set ' . $field . ' to ' . $value . ' for users ' . $users_log);

		clear_interlets_groups_cache();
		
		$alert->success('Het veld werd aangepast.');
		cancel();
	}
	else if (['adr_access' => 1, 'mail_access' => 1, 'tel_access' => 1, 'gsm_access' => 1][$field])
	{
		list($abbrev) = explode('_', $field);

		$id_type_contact = $db->fetchColumn('select id from type_contact where abbrev = ?', [$abbrev]);

		$db->executeUpdate('update contact set flag_public = ? where id_user in (?) and id_type_contact = ?',
			[$access_value, $user_ids, $id_type_contact],
			[\PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY, \PDO::PARAM_INT]);

		log_event('bulk', 'Set ' . $field . ' to ' . $value . ' for users ' . $users_log);
		$alert->success('Het veld werd aangepast.');
		cancel();
	}
}

/**
 * bulk action: mail
 */

if ($s_admin && !count($errors) && ($bulk_mail_submit || $bulk_mail_test) && $post)
{
	$to_log = '';

	$map = [
		'naam' 				=> 'name',
		'volledige_naam'	=> 'fullname',
		'saldo'				=> 'saldo',
		'letscode'			=> 'letscode',
		'postcode'			=> 'postcode',
		'id'				=> 'id',
		'status'			=> 'status',
		'min_limiet'		=> 'minlimit',
		'max_limiet'		=> 'maxlimit',
	];

	if ($bulk_mail_test)
	{
		$sel_ary = [$s_id => true];
		$user_ids = [$s_id];
	}
	else
	{
		$sel_ary = $selected_users;
	}

	$count = 0;
	$users_log = $alert_msg_users = [];

	$sel_users = $db->executeQuery('select u.*, c.value as mail
		from users u, contact c, type_contact tc
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

		$search = $replace = [];

		foreach ($map as $key => $val)
		{
			$search[] = '{{' . $key . '}}';
			$replace[] = ($key == 'status') ? $status_ary[$sel_user['status']] : $sel_user[$val];
		}

		$text = str_replace($search, $replace, $bulk_mail_content);

		mail_q([
			'to' => $sel_user['id'],
			'subject' => $bulk_mail_subject,
			'text' => $text,
			'reply_to' => $s_id,
		]);

		$to_log[] = link_user($sel_user, false, false);
		$alert_msg_users[] = link_user($sel_user);

		$count++;
	}

	if ($count)
	{
		log_event('mail', 'Bulk mail queued, subject: ' . $bulk_mail_subject . ', to: ' . implode(', ', $to_log));

		$alert_msg = 'Mail verzonden naar ' . $count . ' ';
		$alert_msg .= ($count > 1) ? 'accounts' : 'account';
		$alert_msg .= '<br>';
		$alert_msg .= implode('<br>', $alert_msg_users);
		
		$alert->success($alert_msg);
	}
	else
	{
		$alert->warning('Geen mails verzonden.');
	}

	if (count($sel_ary))
	{
		$missing_users = '';

		foreach ($sel_ary as $warning_user_id => $dummy)
		{
			$missing_users .= link_user($warning_user_id) . '<br>';
		}

		$alert->warning('Naar volgende gebruikers werd geen mail verzonden wegens ontbreken van mail adres: <br>' . $missing_users);
	}

	if ($bulk_mail_submit && $count)
	{
		cancel();
	}
}

/**
 * Change password.
 */

if ($pw)
{
	$s_owner = (!$s_guest && $s_group_self && $pw == $s_id && $pw) ? true : false;

	if (!$s_admin && !$s_owner)
	{
		$alert->error('Je hebt onvoldoende rechten om het paswoord aan te passen voor deze gebruiker.');
		cancel($pw);
	}

	if($submit)
	{
		$password = trim($_POST['password']);

		if (empty($password) || (trim($password) == ''))
		{
			$errors[] = 'Vul paswoord in!';
		}

		if (!$s_admin && password_strength($password) < 50) // ignored readconfigfromdb('pwscore')
		{
			$errors[] = 'Te zwak paswoord.';
		}

		if ($error_token = get_error_form_token())
		{
			$errors[] = $error_token;
		}

		if (empty($errors))
		{
			$update = [
				'password'	=> hash('sha512', $password),
				'mdate'		=> gmdate('Y-m-d H:i:s'),
			];

			if ($db->update('users', $update, ['id' => $pw]))
			{
				$user = readuser($pw, true);
				$alert->success('Paswoord opgeslagen.');

				if (($user['status'] == 1 || $user['status'] == 2) && $_POST['notify'])
				{
					$to = $db->fetchColumn('select c.value
						from contact c, type_contact tc
						where tc.id = c.id_type_contact
							and tc.abbrev = \'mail\'
							and c.id_user = ?', [$pw]);

					if ($to)
					{
						$url = $base_url . '/login.php?login=' . $user['letscode'];

						$subj = 'nieuw paswoord voor je account';

						$con = '*** Dit is een automatische mail van ';
						$con .= $systemname;
						$con .= '. Niet beantwoorden a.u.b. ';
						$con .= "***\n\n";
						$con .= 'Beste ' . $user['name'] . ',' . "\n\n";
						$con .= 'Er werd een nieuw paswoord voor je ingesteld.';
						$con .= "\n\n";
						$con .= 'Je kan inloggen met de volgende gegevens:';
						$con .= "\n\nLogin (letscode): " . $user['letscode'];
						$con .= "\nPaswoord: " .$password . "\n\n";
						$con .= 'link waar je kan inloggen: ' . $url;
						$con .= "\n\n";
						$con .= 'Veel letsgenot!';
						mail_q(['to' => $pw, 'subject' => $subj, 'text' => $con]);

						$alert->success('Notificatie mail verzonden');
					}
					else
					{
						$alert->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het paswoord op een andere manier door!');
					}
				}
				cancel($pw);
			}
			else
			{
				$alert->error('Paswoord niet opgeslagen.');
			}
		}
		else
		{
			$alert->error($errors);
		}

	}

	$user = readuser($pw);

	$include_ary[] = 'generate_password.js';

	$h1 = 'Paswoord aanpassen';
	$h1 .= ($s_owner) ? '' : ' voor ' . link_user($user);
	$fa = 'key';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
	echo '<div class="col-sm-10 controls">';
	echo '<div class="input-group">';
	echo '<input type="text" class="form-control" id="password" name="password" ';
	echo 'value="' . $password . '" required>';
	echo '<span class="input-group-btn">';
	echo '<button class="btn btn-default" type="button" id="generate">Genereer</button>';
	echo '</span>';
	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="notify" class="col-sm-2 control-label">Notificatie-mail (enkel mogelijk wanneer status actief is)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="checkbox" name="notify" id="notify"';
	echo ($user['status'] == 1 || $user['status'] == 2) ? ' checked="checked"' : ' readonly';
	echo '>';
	echo '</div>';
	echo '</div>';

	echo aphp('users', ['id' => $pw], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * delete a user.
 */

if ($del)
{
	if (!$s_admin)
	{
		$alert->error('Je hebt onvoldoende rechten om een gebruiker te verwijderen.');
		cancel($del);
	}

	if ($s_id == $del)
	{
		$alert->error('Je kan jezelf niet verwijderen.');
		cancel($del);
	}

	if ($db->fetchColumn('select id from transactions where id_to = ? or id_from = ?', [$del, $del]))
	{
		$alert->error('Een gebruiker met transacties kan niet worden verwijderd.');
		cancel($del);
	}

	$user = readuser($del);

	if (!$user)
	{
		$alert->error('Gebruiker bestaat niet.');
		cancel();
	}

	if ($submit)
	{
		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);
			cancel();
		}

		$pw_name_suffix = substr($_POST['form_token'], 0, 5);
		$password = $_POST['password_' . $pw_name_suffix];

		if ($password)
		{
			$sha512 = hash('sha512', $password);

			if ($s_master)
			{
				$enc_password = getenv('MASTER_PASSWORD');
			}
			else
			{
				$enc_password = $db->fetchColumn('select password from users where id = ?', [$s_id]);
			}

			if ($sha512 == $enc_password)
			{
				$usr = $user['letscode'] . ' ' . $user['name'] . ' [id:' . $del . ']';
				$msgs = '';
				$st = $db->prepare('SELECT id, content, id_category, msg_type
					FROM messages
					WHERE id_user = ?');

				$st->bindValue(1, $del);
				$st->execute();

				while ($row = $st->fetch())
				{
					$msgs .= $row['id'] . ': ' . $row['content'] . ', ';
				}
				$msgs = trim($msgs, '\n\r\t ,;:');

				if ($msgs)
				{
					log_event('user','Delete user ' . $usr . ', deleted Messages ' . $msgs);

					$db->delete('messages', ['id_user' => $del]);
				}

				// remove orphaned images.

				$rs = $db->prepare('SELECT mp.id, mp."PictureFile"
					FROM msgpictures mp
					LEFT JOIN messages m ON mp.msgid = m.id
					WHERE m.id IS NULL');

				$rs->execute();

				while ($row = $rs->fetch())
				{

/** to be handled in background process

					if ($row['PictureFile'])
					{
						$result = $s3->deleteObject([
							'Bucket' => $s3_img,
							'Key'    => $row['PictureFile'],
						]);
					}
**/


					$db->delete('msgpictures', ['id' => $row['id']]);
				}

				// update counts for each category

				$offer_count = $want_count = [];

				$rs = $db->prepare('SELECT m.id_category, count(m.*)
					FROM messages m, users u
					WHERE  m.id_user = u.id
						AND u.status IN (1, 2, 3)
						AND msg_type = 1
					GROUP BY m.id_category');

				$rs->execute();

				while ($row = $rs->fetch())
				{
					$offer_count[$row['id_category']] = $row['count'];
				}

				$rs = $db->prepare('SELECT m.id_category, count(m.*)
					FROM messages m, users u
					WHERE  m.id_user = u.id
						AND u.status IN (1, 2, 3)
						AND msg_type = 0
					GROUP BY m.id_category');

				$rs->execute();

				while ($row = $rs->fetch())
				{
					$want_count[$row['id_category']] = $row['count'];
				}

				$all_cat = $db->fetchAll('SELECT id, stat_msgs_offers, stat_msgs_wanted
					FROM categories
					WHERE id_parent IS NOT NULL');

				foreach ($all_cat as $val)
				{
					$offers = $val['stat_msgs_offers'];
					$wants = $val['stat_msgs_wanted'];
					$cat_id = $val['id'];

					$want_count[$cat_id] = (isset($want_count[$cat_id])) ? $want_count[$cat_id] : 0;
					$offer_count[$cat_id] = (isset($offer_count[$cat_id])) ? $offer_count[$cat_id] : 0;

					if ($want_count[$cat_id] == $wants && $offer_count[$cat_id] == $offers)
					{
						continue;
					}

					$stats = [
						'stat_msgs_offers'	=> ($offer_count[$cat_id]) ?: 0,
						'stat_msgs_wanted'	=> ($want_count[$cat_id]) ?: 0,
					];

					$db->update('categories', $stats, ['id' => $cat_id]);
				}

				//delete contacts
				$db->delete('contact', ['id_user' => $del]);

				//delete userimage from bucket;
/** to be handled in background process

				if ($user['PictureFile'])
				{
					$result = $s3->deleteObject([
						'Bucket' => $s3_img,
						'Key'    => $user['PictureFile'],
					]);
				}
				*
*/

				//delete fullname access record.
				$exdb->del('user_fullname_access', $del);

				//finally, the user
				$db->delete('users', ['id' => $del]);
				$redis->expire($schema . '_user_' . $del, 0);

				$alert->success('De gebruiker is verwijderd.');

				if ($user['status'] == 1 || $user['status'] == 2)
				{
					invalidate_typeahead_thumbprint('users_active');
				}
				else if ($user['status'] == 7)
				{
					invalidate_typeahead_thumbprint('users_extern');
				}

				clear_interlets_groups_cache();

				cancel();
			}
			else
			{
				$alert->error('Het paswoord is niet correct.');
			}
		}
		else
		{
			$alert->error('Het paswoord is niet ingevuld.');
		}
	}

	$form_token = generate_form_token(false);
	$pw_name_suffix = substr($form_token, 0, 5);

	$h1 = 'Gebruiker ' . link_user($del) . ' verwijderen?';
	$fa = 'user';

	include $rootpath . 'includes/inc_header.php';

	echo '<p><font color="red">Alle gegevens, Vraag en aanbod, contacten en afbeeldingen van ' . $user['letscode'] . ' ' . $user['name'];
	echo ' worden verwijderd.</font></p>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="password" class="col-sm-2 control-label">Je paswoord (extra veiligheid)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="password" class="form-control" id="password" name="password_' . $pw_name_suffix . '" ';
	echo 'value="" required autocomplete="off">';
	echo '</div>';
	echo '</div>';

	echo aphp('users', ['id' => $del], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * Edit or add a user
 */

if ($add || $edit)
{
	if ($add && !$s_admin)
	{
		$alert->error('Je hebt geen rechten om een gebruiker toe te voegen.');
		cancel();
	}

	$s_owner =  (!$s_guest && $s_group_self && $edit && $s_id && $edit == $s_id && $edit) ? true : false;

	if ($edit && !$s_admin && !$s_owner)
	{
		$alert->error('Je hebt geen rechten om deze gebruiker aan te passen.');
		cancel($edit);
	}

	if ($s_admin)
	{
		$username_edit = $fullname_edit = true;
	}
	else if ($s_owner)
	{
		$username_edit = readconfigfromdb('users_can_edit_username');
		$fullname_edit = readconfigfromdb('users_can_edit_fullname');
	}
	else
	{
		$username_edit = $fullname_edit = false;
	}	

	if ($submit)
	{
		$user = [
			'postcode'		=> trim($_POST['postcode']),
			'birthday'		=> trim($_POST['birthday']) ?: null,
			'hobbies'		=> trim($_POST['hobbies']),
			'comments'		=> trim($_POST['comments']),
			'cron_saldo'	=> $_POST['cron_saldo'] ? 1 : 0,
			'lang'			=> 'nl'
		];

		if ($s_admin)
		{
			$user += [
				'letscode'		=> trim($_POST['letscode']),
				'accountrole'	=> $_POST['accountrole'],
				'status'		=> $_POST['status'],
				'admincomment'	=> trim($_POST['admincomment']),
				'minlimit'		=> trim($_POST['minlimit']),
				'maxlimit'		=> trim($_POST['maxlimit']),
				'presharedkey'	=> trim($_POST['presharedkey']),
			];

			$contact = $_POST['contact'];
			$notify = $_POST['notify'];
			$password = trim($_POST['password']);

			$mail_unique_check_sql = 'select count(c.value)
					from contact c, type_contact tc, users u
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

			$st = $db->prepare($mail_unique_check_sql);

			foreach ($contact as $key => $c)
			{
				$contact[$key]['flag_public'] = $access_control->get_post_value('contact_access_' . $key);

				if ($c['value'])
				{
					$contact_post_error = $access_control->get_post_error('contact_access_' . $key);

					if ($contact_post_error)
					{
						$errors[] = $contact_post_error;
					}
				}
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

						if ($row['count'] == 1)
						{
							$errors[] = 'Emailadres ' . $mailadr . ' bestaat al onder de actieve gebruikers.';
						}
						else if ($row['count'] > 1)
						{
							$errors[] = 'Emailadres ' . $mailadr . ' bestaat al ' . $row['count'] . ' maal onder de actieve gebruikers.';
						}
					}
				}
			}

			if ($user['status'] == 1 || $user['status'] == 2)
			{
				if (!$mailadr)
				{
					$alert->warning('Waarschuwing: Geen mailadres ingevuld. De gebruiker kan geen berichten en notificaties ontvangen en zijn/haar paswoord niet resetten.');
				}
			}

			$letscode_sql = 'select letscode
				from users
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

		$fullname_access = $access_control->get_post_value('fullname_access');

		$name_sql = 'select name
			from users
			where name = ?';
		$name_sql_params = [$user['name']];

		$fullname_sql = 'select fullname
			from users
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

			$user_prefetch = readuser($edit);
		}

		$fullname_access_error = $access_control->get_post_error('fullname_access');

		if ($fullname_access_error)
		{
			$errors[] = $fullname_access_error;
		}

		if ($username_edit)
		{
			if (!$user['name'])
			{
				$errors[] = 'Vul gebruikersnaam in!';
			}
			else if ($db->fetchColumn($name_sql, $name_sql_params))
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
				$errors[] = 'Vul de volledige naam in!';
			}

			if ($db->fetchColumn($fullname_sql, $fullname_sql_params))
			{
				$errors[] = 'De volledige naam is al in gebruik!';
			}

			if (strlen($user['fullname']) > 100)
			{
				$errors[] = 'De volledige naam mag maximaal 100 tekens lang zijn.';
			}
		}

		if ($s_admin)
		{
			if (!$user['letscode'])
			{
				$errors[] = 'Vul een letscode in!';
			}
			else if ($db->fetchColumn($letscode_sql, $letscode_sql_params))
			{
				$errors[] = 'De letscode bestaat al!';
			}
			else if ($user['letscode'] == '-')
			{
				$errors[] = 'Letscode - is gereserveerd voor de interlets gast gebruikers';
			}
			else if (strlen($user['letscode']) > 20)
			{
				$errors[] = 'De letscode mag maximaal 20 tekens lang zijn.';
			}

			if (!preg_match("/^[A-Za-z0-9-]+$/", $user['letscode']))
			{
				$errors[] = 'De letscode kan enkel uit letters, cijfers en koppeltekens bestaan.';
			}

			if (!($user['minlimit'] == 0 || filter_var($user['minlimit'], FILTER_VALIDATE_INT)))
			{
				$errors[] = 'Geef getal op voor de minimum limiet.';
			}

			if (!($user['maxlimit'] == 0 || filter_var($user['maxlimit'], FILTER_VALIDATE_INT)))
			{
				$errors[] = 'Geef getal op voor de maximum limiet.';
			}

			if (strlen($user['presharedkey']) > 80)
			{
				$errors[] = 'De preshared key mag maximaal 80 tekens lang zijn.';
			}
		}

		if ($user['birthday'])
		{
			$user['birthday'] = $date_format->reverse($user['birthday']);

			if ($user['birthday'] === false)
			{
				$errors[] = 'Fout in formaat geboortedag.';
				$user['birthday'] = '';
			}
		}

		if (strlen($user['comments']) > 100)
		{
			$errors[] = 'Commentaar mag maximaal 100 tekens lang zijn.';
		}

		if (strlen($user['postcode']) > 6)
		{
			$errors[] = 'De postcode mag maximaal 6 tekens lang zijn.';
		}

		if (strlen($user['hobbies']) > 500)
		{
			$errors[] = 'Het veld hobbies en interesses mag maximaal 500 tekens lang zijn.';
		}

		if ($s_admin && !$user_prefetch['adate'] && $user['status'] == 1)
		{
			if (!$password)
			{
				$errors[] = 'Gelieve een paswoord in te vullen.';
			}
			else if (!password_strength($password))
			{
				$errors[] = 'Het paswoord is niet sterk genoeg.';
			}
		}

		if ($error_token = get_error_form_token())
		{
			$errors[] = $error_token;
		}

		if (!count($errors))
		{
			$contact_types = [];

			$rs = $db->prepare('SELECT abbrev, id FROM type_contact');

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$contact_types[$row['abbrev']] = $row['id'];
			}

			if ($add)
			{
				$user['creator'] = ($s_master) ? 0 : $s_id;

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

				if ($db->insert('users', $user))
				{
					$id = $db->lastInsertId('users_id_seq');

					$fullname_access_role = $access_control->get_role($fullname_access);

					$exdb->set('user_fullname_access', $id, ['fullname_access' => $fullname_access_role]);

					$alert->success('Gebruiker opgeslagen.');

					$user = readuser($id, true);

					foreach ($contact as $value)
					{
						if (!$value['value'])
						{
							continue;
						}

						$insert = [
							'value'				=> trim($value['value']),
							'flag_public'		=> $value['flag_public'],
							'id_type_contact'	=> $contact_types[$value['abbrev']],
							'id_user'			=> $id,
						];

						$db->insert('contact', $insert);
					}

					if ($user['status'] == 1)
					{
						if ($notify && $mailadr && $user['status'] == 1 && $password)
						{
							$user['mail'] = $mailadr;

							if (readconfigfromdb('mailenabled'))
							{
								sendactivationmail($password, $user);
								sendadminmail($user);
								$alert->success('Mail met paswoord naar de gebruiker verstuurd.');
							}
							else
							{
								$alert->warning('Mailfuncties zijn uitgeschakeld. Geen mail met paswoord naar de gebruiker verstuurd.');
							}
						}
						else
						{
							$alert->warning('Geen mail met paswoord naar de gebruiker verstuurd.');
						}
					}

					if ($user['status'] == 2 | $user['status'] == 1)
					{
						invalidate_typeahead_thumbprint('users_active');
					}

					if ($user['status'] == 7)
					{
						invalidate_typeahead_thumbprint('users_extern');
					}

					clear_interlets_groups_cache();

					cancel($id);
				}
				else
				{
					$alert->error('Gebruiker niet opgeslagen.');
				}
			}
			else if ($edit)
			{
				$user_stored = readuser($edit);

				$user['mdate'] = gmdate('Y-m-d H:i:s');

				if (!$user_stored['adate'] && $user['status'] == 1)
				{
					$user['adate'] = gmdate('Y-m-d H:i:s');

					if ($password)
					{
						$user['password'] = hash('sha512', $password);
					}
				}

				if($db->update('users', $user, ['id' => $edit]))
				{

					$fullname_access_role = $access_control->get_role($fullname_access);

					$exdb->set('user_fullname_access', $edit, ['fullname_access' => $fullname_access_role]);

					$user = readuser($edit, true);

					$alert->success('Gebruiker aangepast.');

					if ($s_admin)
					{
						$stored_contacts = [];

						$rs = $db->prepare('SELECT c.id, tc.abbrev, c.value, c.flag_public
							FROM type_contact tc, contact c
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
									$db->delete('contact', ['id_user' => $edit, 'id' => $value['id']]);
								}
								continue;
							}

							if ($stored_contact['abbrev'] == $value['abbrev']
								&& $stored_contact['value'] == $value['value']
								&& $stored_contact['flag_public'] == $value['flag_public'])
							{
								continue;
							}

							if (!isset($stored_contact))
							{
								$insert = [
									'id_type_contact'	=> $contact_types[$value['abbrev']],
									'value'				=> trim($value['value']),
									'flag_public'		=> $value['flag_public'],
									'id_user'			=> $edit,
								];
								$db->insert('contact', $insert);
								continue;
							}

							$contact_update = $value;

							unset($contact_update['id'], $contact_update['abbrev'],
								$contact_update['name'], $contact_update['main_mail']);

							$db->update('contact', $contact_update,
								['id' => $value['id'], 'id_user' => $edit]);
						}


						if ($user['status'] == 1 && !$user_prefetch['adate'])
						{
							if ($notify && !empty($mail) && $password)
							{
								if (readconfigfromdb('mailenabled'))
								{
									$user['mail'] = $mail;
									sendactivationmail($password, $user);
									sendadminmail($user);
									$alert->success('Mail met paswoord naar de gebruiker verstuurd.');
								}
								else
								{
									$alert->warning('De mailfuncties zijn uitgeschakeld. Geen mail met paswoord naar de gebruiker verstuurd.');
								}
							}
							else
							{
								$alert->warning('Geen mail met paswoord naar de gebruiker verstuurd.');
							}
						}

						if ($user['status'] == 1
							|| $user['status'] == 2
							|| $user_stored['status'] == 1
							|| $user_stored['status'] == 2)
						{
							invalidate_typeahead_thumbprint('users_active');
						}

						if ($user['status'] == 7
							|| $user_stored['status'] == 7)
						{
							invalidate_typeahead_thumbprint('users_extern');
						}

						clear_interlets_groups_cache();
					}
					cancel($edit);
				}
				else
				{
					$alert->error('Gebruiker niet aangepast.');
				}
			}
		}
		else
		{
			$alert->error($errors);

			if ($edit)
			{
				$user['adate'] = $user_prefetch['adate'];
			}
		}
	}
	else
	{
		if ($edit)
		{
			$user = readuser($edit);
			$fullname_access = $user['fullname_access'];
		}

		if ($s_admin)
		{
			$contact = $db->fetchAll('select name, abbrev, \'\' as value, 0 as id
				from type_contact
				where abbrev in (\'mail\', \'adr\', \'tel\', \'gsm\')');
		}

		if ($edit && $s_admin)
		{
			$contact_keys = [];

			foreach ($contact as $key => $c)
			{
				$contact_keys[$c['abbrev']] = $key;
			}

			$st = $db->prepare('SELECT tc.abbrev, c.value, tc.name, c.flag_public, c.id
				FROM type_contact tc, contact c
				WHERE tc.id = c.id_type_contact
					AND c.id_user = ?');

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
		else if ($s_admin)
		{
			$user = [
				'minlimit'		=> readconfigfromdb('minlimit'),
				'maxlimit'		=> readconfigfromdb('maxlimit'),
				'accountrole'	=> 'user',
				'status'		=> '1',
				'cron_saldo'	=> 1,
			];

			if ($interlets)
			{
				if ($group = $db->fetchAssoc('select *
					from letsgroups
					where localletscode = ?
						and apimethod <> \'internal\'', [$interlets]))
				{
					$user['name'] = $user['fullname'] = $group['groupname'];

					if ($group['url'] && ($remote_schema = $schemas[$group['url']]))
					{
						$group['domain'] = get_host($group);

						if (isset($schemas[$group['domain']]))
						{
							$remote_schema = $schemas[$group['domain']];

							$admin_mail = readconfigfromdb('admin', $remote_schema);

							foreach ($contact as $k => $c)
							{
								if ($c['abbrev'] == 'mail')
								{
									$contact[$k]['value'] = $admin_mail;
									break;
								}
							}

							// name from source is preferable
							$user['name'] = $user['fullname'] = readconfigfromdb('systemname', $remote_schema);
						}
					}
				}

				$user['cron_saldo'] = 0;
				$user['status'] = '7';
				$user['accountrole'] = 'interlets';
				$user['letscode'] = $interlets;
			}
			else
			{
				$user['cron_saldo'] = 1;
				$user['status'] = '1';
				$user['accountrole'] = 'user';
			}
		}
	}

	array_walk($user, function(&$value, $key){ $value = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')); });
	array_walk($contact, function(&$value, $key){ $value['value'] = trim(htmlspecialchars($value['value'], ENT_QUOTES, 'UTF-8')); });

	$top_buttons .= aphp('users', ['status' => 'active', 'view' => $view_users], 'Lijst', 'btn btn-default', 'Lijst', 'users', true);

	$include_ary[] = 'datepicker';
	$include_ary[] = 'generate_password.js';
	$include_ary[] = 'generate_password_onload.js';
	$include_ary[] = 'user_edit.js';
	$include_ary[] = 'access_input_cache.js';

	$h1 = 'Gebruiker ' . (($edit) ? 'aanpassen: ' . link_user($user) : 'toevoegen');
	$h1 = ($s_owner && !$s_admin && $edit) ? 'Je profiel aanpassen' : $h1;
	$fa = 'user';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	if ($s_admin)
	{
		echo '<div class="form-group">';
		echo '<label for="letscode" class="col-sm-2 control-label">Letscode</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="letscode" name="letscode" ';
		echo 'value="' . $user['letscode'] . '" required maxlength="20">';
		echo '</div>';
		echo '</div>';
	}

	if ($username_edit)
	{
		echo '<div class="form-group">';
		echo '<label for="name" class="col-sm-2 control-label">Gebruikersnaam</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="name" name="name" ';
		echo 'value="' . $user['name'] . '" required maxlength="50">';
		echo '</div>';
		echo '</div>';
	}

	if ($fullname_edit)
	{
		echo '<div class="form-group">';
		echo '<label for="fullname" class="col-sm-2 control-label">Volledige naam (Voornaam en Achternaam)</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="fullname" name="fullname" ';
		echo 'value="' . $user['fullname'] . '" maxlength="100">';
		echo '</div>';
		echo '</div>';
	}

	if (!isset($fullname_access))
	{
		$fullname_access = ($add && !$interlets) ? false : 'admin';
	}

	echo $access_control->get_radio_buttons('users_fullname', $fullname_access, false, 'fullname_access', 'xs', 'Zichtbaarheid volledige naam');

	echo '<div class="form-group">';
	echo '<label for="postcode" class="col-sm-2 control-label">Postcode</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="postcode" name="postcode" ';
	echo 'value="' . $user['postcode'] . '" required maxlength="6">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="birthday" class="col-sm-2 control-label">Geboortedatum</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="birthday" name="birthday" ';
	echo 'value="';
	echo $user['birthday'] ? $date_format->get($user['birthday'], 'day') : '';
	echo '" ';
	echo 'data-provide="datepicker" ';
	echo 'data-date-format="' . $date_format->datepicker_format() . '" ';
	echo 'data-date-default-view="2" ';
	echo 'data-date-end-date="' . $date_format->get(false, 'day') . '" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-start-view="2" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-immediate-updates="true" ';
	echo 'data-date-orientation="bottom" ';
	echo 'placeholder="' . $date_format->datepicker_placeholder() . '"';
	echo '>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="hobbies" class="col-sm-2 control-label">Hobbies, interesses</label>';
	echo '<div class="col-sm-10">';
	echo '<textarea name="hobbies" id="hobbies" class="form-control" maxlength="500">';
	echo $user['hobbies'];
	echo '</textarea>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="comments" class="col-sm-2 control-label">Commentaar</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="comments" name="comments" ';
	echo 'value="' . $user['comments'] . '">';
	echo '</div>';
	echo '</div>';

	if ($s_admin)
	{
		echo '<div class="form-group">';
		echo '<label for="accountrole" class="col-sm-2 control-label">Rechten / Rol</label>';
		echo '<div class="col-sm-10">';
		echo '<select id="accountrole" name="accountrole" class="form-control">';
		render_select_options($role_ary, $user['accountrole']);
		echo '</select>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="status" class="col-sm-2 control-label">Status</label>';
		echo '<div class="col-sm-10">';
		echo '<select id="status" name="status" class="form-control">';
		render_select_options($status_ary, $user['status']);
		echo '</select>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group" id="presharedkey_formgroup">';
		echo '<label for="presharedkey" class="col-sm-2 control-label">';
		echo 'Preshared key (enkel voor interletsaccount met eLAS-installatie)</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="presharedkey" name="presharedkey" ';
		echo 'value="' . $user['presharedkey'] . '" maxlength="80">';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="admincomment" class="col-sm-2 control-label">Commentaar van de admin</label>';
		echo '<div class="col-sm-10">';
		echo '<textarea name="admincomment" id="admincomment" class="form-control" maxlength="200">';
		echo $user['admincomment'];
		echo '</textarea>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="minlimit" class="col-sm-2 control-label">Minimum limiet saldo</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="number" class="form-control" id="minlimit" name="minlimit" ';
		echo 'value="' . $user['minlimit'] . '">';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="maxlimit" class="col-sm-2 control-label">Maximum limiet saldo</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="number" class="form-control" id="maxlimit" name="maxlimit" ';
		echo 'value="' . $user['maxlimit'] . '">';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<label for="cron_saldo" class="col-sm-2 control-label">Periodieke mail met recent vraag en aanbod</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="checkbox" name="cron_saldo" id="cron_saldo"';
	echo ($user['cron_saldo']) ? ' checked="checked"' : '';
	echo '>';
	echo '</div>';
	echo '</div>';

	if ($s_admin)
	{
		echo '<div class="bg-warning">';
		echo '<h2><i class="fa fa-map-marker"></i> Contacten</h2>';

		foreach ($contact as $key => $c)
		{
			$name = 'contact[' . $key . '][value]';

			echo '<div class="form-group">';
			echo '<label for="' . $name . '" class="col-sm-2 control-label">' . $c['abbrev'] . '</label>';
			echo '<div class="col-sm-10">';
			echo '<input class="form-control" id="' . $name . '" name="' . $name . '" ';
			echo 'value="' . $c['value'] . '"';
			echo ($c['abbrev'] == 'mail') ? ' type="email"' : ' type="text"';
			echo ' data-access="contact_access_' . $key . '">';
			echo '</div>';
			echo '</div>';

			if (!isset($c['flag_public']))
			{
				$c['flag_public'] = false;
			}

			echo $access_control->get_radio_buttons($c['abbrev'], $c['flag_public'], false, 'contact_access_' . $key);

			echo '<input type="hidden" name="contact['. $key . '][id]" value="' . $c['id'] . '">';
			echo '<input type="hidden" name="contact['. $key . '][name]" value="' . $c['name'] . '">';
			echo '<input type="hidden" name="contact['. $key . '][abbrev]" value="' . $c['abbrev'] . '">';

			echo '<hr>';
		}

		echo '<p><small>Meer contacten kunnen toegevoegd worden vanuit de profielpagina met de knop ';
		echo 'Toevoegen bij de contactinfo ';
		echo ($add) ? 'nadat de gebruiker gecreëerd is' : '';
		echo '.</small></p>';
		echo '</div>';

		if (!$user['adate'] && $s_admin)
		{
			echo '<div id="activate">';

			echo '<div class="form-group">';
			echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
			echo '<div class="col-sm-10 controls">';
			echo '<div class="input-group">';
			echo '<input type="text" class="form-control" id="password" name="password" ';
			echo 'value="' . $password . '" required>';
			echo '<span class="input-group-btn">';
			echo '<button class="btn btn-default" type="button" id="generate">Genereer</button>';
			echo '</span>';
			echo '</div>';
			echo '</div>';
			echo '</div>';

			echo '<div class="form-group">';
			echo '<label for="notify" class="col-sm-2 control-label">Zend mail met paswoord naar gebruiker (enkel wanneer account actief is.)</label>';
			echo '<div class="col-sm-10">';
			echo '<input type="checkbox" name="notify" id="notify"';
			echo ' checked="checked"';
			echo '>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}
	}

	$canc = ($edit) ? ['id' => $edit] : ['status' => 'active', 'view' => $view_users];
	$btn = ($edit) ? 'primary' : 'success';
	echo aphp('users', $canc, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/*
 * Show a user
 */

if ($id)
{
	$s_owner = (!$s_guest && $s_group_self && $s_id == $id && $id) ? true : false;

	$user_mail_cc = ($post) ? $user_mail_cc : 1;

	$user = readuser($id);

	if (!$s_admin && !in_array($user['status'], [1, 2]))
	{
		$alert->error('Je hebt geen toegang tot deze gebruiker.');
		cancel();
	}

	if ($s_admin)
	{
		$count_transactions = $db->fetchColumn('select count(*)
			from transactions
			where id_from = ?
				or id_to = ?', [$id, $id]);
	}

	$mail_to = getmailadr($user['id']);
	$mail_from = ($s_schema && !$s_master) ? getmailadr($s_schema . '.' . $s_id) : [];

	$and_status = ($s_admin) ? '' : ' and status in (1, 2) ';

	$next = $db->fetchColumn('select id
		from users
		where letscode > ?
		' . $and_status . '
		order by letscode asc
		limit 1', [$user['letscode']]);

	$prev = $db->fetchColumn('select id
		from users
		where letscode < ?
		' . $and_status . '
		order by letscode desc
		limit 1', [$user['letscode']]);

	$include_ary[] = 'leaflet';
	$include_ary[] = 'jqplot';
	$include_ary[] = 'user.js';
	$include_ary[] = 'plot_user_transactions.js';

	if ($s_admin || $s_owner)
	{
		$include_ary[] = 'fileupload';
		$include_ary[] = 'user_img.js';
	}

	if ($s_admin)
	{
		$top_buttons .= aphp('users', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Gebruiker toevoegen', 'plus', true);
	}

	if ($s_admin || $s_owner)
	{
		$title = ($s_admin) ? 'Gebruiker' : 'Mijn gegevens';
		$top_buttons .= aphp('users', ['edit' => $id], 'Aanpassen', 'btn btn-primary', $title . ' aanpassen', 'pencil', true);
		$top_buttons .= aphp('users', ['pw' => $id], 'Paswoord aanpassen', 'btn btn-info', 'Paswoord aanpassen', 'key', true);
	}

	if ($s_admin && !$count_transactions && !$s_owner)
	{
		$top_buttons .= aphp('users', ['del' => $id], 'Verwijderen', 'btn btn-danger', 'Gebruiker verwijderen', 'times', true);
	}

	if ($s_admin
		|| (!$s_owner && $user['status'] != 7 && !($s_guest && $s_group_self)))
	{
			$tus = ['add' => 1, 'tuid' => $id];

			if (!$s_group_self)
			{
				$tus['tus'] = $schema;
			}

			$top_buttons .= aphp('transactions', $tus, 'Transactie',
				'btn btn-warning', 'Transactie naar ' . link_user($user, false, false),
				'exchange', true, false, $s_schema);
	}

	if ($prev)
	{
		$top_buttons .= aphp('users', ['id' => $prev], 'Vorige', 'btn btn-default', 'Vorige', 'chevron-up', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('users', ['id' => $next], 'Volgende', 'btn btn-default', 'Volgende', 'chevron-down', true);
	}

	$top_buttons .= aphp('users', ['status' => 'active', 'view' => $view_users], 'Lijst', 'btn btn-default', 'Lijst', 'users', true);

	$status = $user['status'];
	$status = ($newusertreshold < strtotime($user['adate']) && $status == 1) ? 3 : $status;

	$status_style_ary = [
		0	=> 'default',
		2	=> 'danger',
		3	=> 'success',
		5	=> 'warning',
		6	=> 'info',
		7	=> 'extern',
	];

	$h_status_ary = $status_ary;
	$h_status_ary[3] = 'Instapper';

	$h1 = (($s_owner && !$s_admin) ? 'Mijn gegevens: ' : '') . link_user($user);

	if ($status != 1)
	{
		$h1 .= ' <small><span class="text-' . $status_style_ary[$status] . '">';
		$h1 .= $h_status_ary[$status] . '</span></small>';
	}

	$fa = 'user';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="row">';
	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-body text-center center-block" id="img_user">';

	$show_img = ($user['PictureFile']) ? true : false;

	$user_img = ($show_img) ? '' : ' style="display:none;"';
	$no_user_img = ($show_img) ? ' style="display:none;"' : '';

	$img_src = ($user['PictureFile']) ? $s3_img_url . $user['PictureFile'] : $rootpath . 'gfx/1.gif';
	echo '<img id="user_img"' . $user_img . ' class="img-rounded img-responsive center-block" ';
	echo 'src="' . $img_src . '" ';
	echo 'data-bucket-url="' . $s3_img_url . '"></img>';

	echo '<div id="no_user_img"' . $no_user_img . '>';
	echo '<i class="fa fa-user fa-5x text-muted"></i><br>Geen profielfoto</div>';

	echo '</div>';

	if ($s_admin || $s_owner)
	{
		$attr = ['id'	=> 'btn_remove'];
		if (!$user['PictureFile'])
		{
			$attr['style'] = 'display:none;';
		}

		echo '<div class="panel-footer"><span class="btn btn-success fileinput-button">';
		echo '<i class="fa fa-plus" id="img_plus"></i> Foto opladen';
		echo '<input id="fileupload" type="file" name="image" ';
		echo 'data-url="' . generate_url('users', ['img' => 1, 'id' => $id]) . '" ';
		echo 'data-data-type="json" data-auto-upload="true" ';
		echo 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
		echo 'data-max-file-size="999000" data-image-max-width="400" ';
		echo 'data-image-crop="true" ';
		echo 'data-image-max-height="400"></span>&nbsp;';

		echo aphp('users', ['img_del' => 1, 'id' => $id], 'Foto verwijderen', 'btn btn-danger', false, 'times', false, $attr);

		echo '<p class="text-warning">Je foto moet in het jpg/jpeg formaat zijn. ';
		echo 'Je kan ook een foto hierheen verslepen.</p>';
		echo '</div>';
	}

	echo '</div></div>';

	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';
	echo '<dl>';

	$fullname_access = ($user['fullname_access']) ?: 'admin';

	if ($s_admin || $s_owner || $access_control->is_visible($fullname_access))
	{
		echo '<dt>';
		echo 'Volledige naam';
		echo '</dt>';
		dd_render($user['fullname']);

		echo '<dt>Zichtbaarheid volledige naam</dt>';
		echo '<dd>';
		echo $access_control->get_label($fullname_access);
		echo '</dd>';
	}

	echo '<dt>';
	echo 'Postcode';
	echo '</dt>';
	dd_render($user['postcode']);

	if ($s_admin || $s_owner)
	{
		echo '<dt>';
		echo 'Geboortedatum';
		echo '</dt>';
		if (isset($user['birthday']))
		{
			dd_render($date_format->get($user['birthday'], 'day'));
		}
		else
		{
			echo '<dd><i class="fa fa-times"></i></dd>';
		}
	}

	echo '<dt>';
	echo 'Hobbies / Interesses';
	echo '</dt>';
	dd_render($user['hobbies']);

	echo '<dt>';
	echo 'Commentaar';
	echo '</dt>';
	dd_render($user['comments']);

	if ($s_admin)
	{
		echo '<dt>';
		echo 'Tijdstip aanmaak';
		echo '</dt>';

		if (isset($user['cdate']))
		{
			dd_render($date_format->get($user['cdate']));
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
			dd_render($date_format->get($user['adate']));
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
			dd_render($date_format->get($user['lastlogin']));
		}
		else
		{
			echo '<dd><i class="fa fa-times"></i></dd>';
		}

		echo '<dt>';
		echo 'Rechten';
		echo '</dt>';
		dd_render($user['accountrole']);

		echo '<dt>';
		echo 'Status';
		echo '</dt>';
		dd_render($status_ary[$user['status']]);

		echo '<dt>';
		echo 'Commentaar van de admin';
		echo '</dt>';
		dd_render($user['admincomment']);
	}

	echo '<dt>Saldo</dt>';
	echo '<dd>';
	echo '<span class="label label-info">' . $user['saldo'] . '</span>&nbsp;';
	echo $currency;
	echo '</dd>';

	echo '<dt>Minimum limiet</dt>';
	echo '<dd>';
	echo '<span class="label label-danger">' . $user['minlimit'] . '</span>&nbsp;';
	echo $currency;
	echo '</dd>';

	echo '<dt>Maximum limiet</dt>';
	echo '<dd>';
	echo '<span class="label label-success">' . $user['maxlimit'] . '</span>&nbsp;';
	echo $currency;
	echo '</dd>';

	if ($s_admin || $s_owner)
	{
		echo '<dt>';
		echo 'Periodieke mail met recent vraag en aanbod';
		echo '</dt>';
		dd_render(($user['cron_saldo']) ? 'Aan' : 'Uit');
		echo '</dl>';
	}

	echo '</div></div></div></div>';

	echo '<div id="contacts" '; //data-uid="' . $id . '" ';
	echo 'data-url="' . $rootpath . 'contacts.php?inline=1&uid=' . $id;
	echo '&' . http_build_query(get_session_query_param()) . '"></div>';

	// response form

	if ($s_elas_guest)
	{
		$placeholder = 'Als eLAS gast kan je niet het mail formulier gebruiken.';
	}
	else if ($s_owner)
	{
		$placeholder = 'Je kan geen berichten naar jezelf mailen.';
	}
	else if (!count($mail_to))
	{
		$placeholder = 'Er is geen email adres bekend van deze gebruiker.';
	}
	else if (!count($mail_from))
	{
		$placeholder = 'Om het mail formulier te gebruiken moet een mail adres ingesteld zijn voor je eigen account.';
	}
	else
	{
		$placeholder = '';
	}

	$disabled = (!$s_schema || !count($mail_to) || !count($mail_from) || $s_owner) ? true : false;

	echo '<h3><i class="fa fa-envelop-o"></i> Stuur een bericht naar ';
	echo  link_user($id) . '</h3>';
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<textarea name="user_mail_content" rows="6" placeholder="' . $placeholder . '" ';
	echo 'class="form-control" required';
	echo ($disabled) ? ' disabled' : '';
	echo '>' . ((isset($user_mail_content)) ? $user_mail_content : '') . '</textarea>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<input type="checkbox" name="user_mail_cc"';
	echo ($user_mail_cc) ? ' checked="checked"' : '';
	echo ' value="1" >Stuur een kopie naar mijzelf';
	echo '</div>';
	echo '</div>';

	echo '<input type="submit" name="user_mail_submit" value="Versturen" class="btn btn-default"';
	echo ($disabled) ? ' disabled' : '';
	echo '>';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	//

	echo '<div class="row">';
	echo '<div class="col-md-12">';
	echo '<h3>Saldo: <span class="label label-info">' . $user['saldo'] . '</span> ';
	echo $currency . '</h3>';
	echo '</div></div>';

	echo '<div class="row print-hide">';
	echo '<div class="col-md-6">';
	echo '<div id="chartdiv" data-height="480px" data-width="960px" ';
	echo 'data-url="' . $rootpath . 'ajax/plot_user_transactions.php?id=' . $id;
	echo '&' . http_build_query(get_session_query_param()) . '" ';
	echo 'data-users-url="' . $rootpath . 'users.php?id=" ';
	echo 'data-transactions-url="' . $rootpath . 'transactions.php?id=" ';
	echo 'data-session-query-param="' . http_build_query(get_session_query_param()) . '" ';
	echo 'data-user-id="' . $id . '"></div>';
	echo '</div>';
	echo '<div class="col-md-6">';
	echo '<div id="donutdiv" data-height="480px" data-width="960px"></div>';
	echo '<h4>Interacties laatste jaar</h4>';
	echo '</div>';
	echo '</div>';

	if ($user['status'] == 1 || $user['status'] == 2)
	{
		echo '<div id="messages" ';
		echo 'data-url="' . $rootpath . 'messages.php?inline=1&uid=' . $id;
		echo '&' . http_build_query(get_session_query_param()) . '" class="print-hide"></div>';
	}

	echo '<div id="transactions" ';
	echo 'data-url="' . $rootpath . 'transactions.php?inline=1&uid=' . $id;
	echo '&' . http_build_query(get_session_query_param()) . '" class="print-hide"></div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/*
 * List all users
 */

if (!$view)
{
	cancel();
}

$v_list = ($view == 'list') ? true : false;
$v_extended = ($view == 'extended') ? true : false;
$v_tiles = ($view == 'tiles') ? true : false;
$v_map = ($view == 'map') ? true : false;

$st = [
	'active'	=> [
		'lbl'	=> 'Actief',
		'sql'	=> 'u.status in (1, 2)',
		'st'	=> [1, 2],
	],
	'new'		=> [
		'lbl'	=> 'Instappers',
		'sql'	=> 'u.status = 1 and u.adate > ?',
		'sql_bind'	=> gmdate('Y-m-d H:i:s', $newusertreshold),
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

if ($s_admin)
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

$st_class_ary = [
	0 => 'inactive',
	2 => 'danger',
	3 => 'success',
	5 => 'warning',
	6 => 'info',
	7 => 'extern',
];

$sql_bind = [];
$params = [];

if (!isset($st[$status]))
{
	cancel();
}

if (isset($st[$status]['sql_bind']))
{
	$sql_bind[] = $st[$status]['sql_bind'];
}

$params = [
	'status'	=> $status,
	'view'		=> $view,
];

if ($v_list && $s_admin)
{
	if (isset($_GET['sh']))
	{
		$show_columns = $_GET['sh'];
	}
	else
	{
		$show_columns = [
			'u'	=> [
				'letscode'	=> 1,
				'name'		=> 1,
				'postcode'	=> 1,
				'saldo'		=> 1,
			],
		];
	}

	$adr_split = isset($_GET['adr_split']) ? $_GET['adr_split'] : '';
	$activity_days = isset($_GET['activity_days']) ? $_GET['activity_days'] : 365;
	$activity_days = ($activity_days < 1) ? 365 : $activity_days;
	$activity_filter_letscode = isset($_GET['activity_filter_letscode']) ? $_GET['activity_filter_letscode'] : '';
	$saldo_date = isset($_GET['saldo_date']) ? trim($_GET['saldo_date']) : '';

	$type_contact = $db->fetchAll('select id, abbrev, name from type_contact');

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
			'admincomment'	=> 'Admin commentaar',
			'hobbies'		=> 'Hobbies/interesses',
			'cron_saldo'	=> 'Periodieke mail',
			'cdate'			=> 'Gecreëerd',
			'mdate'			=> 'Aangepast',
			'adate'			=> 'Geactiveerd',
			'lastlogin'		=> 'Laatst ingelogd',
		],
	];

	foreach ($type_contact as $tc)
	{
		$columns['c'][$tc['abbrev']] = $tc['name'];
	}

	$columns['m'] = [
		'demands'	=> 'Vraag',
		'offers'	=> 'Aanbod',
		'total'		=> 'Vraag en aanbod',
	];

	$columns['a'] = [
		'trans_in'		=> 'Transacties in',
		'trans_out'		=> 'Transacties uit',
		'trans_total'	=> 'Transacties totaal',
		'amount_in'		=> $currency . ' in',
		'amount_out'	=> $currency . ' uit',
		'amount_total'	=> $currency . ' totaal',
	];

	$users = $db->fetchAll('select u.*
		from users u
		where ' . $st[$status]['sql'], $sql_bind);

	if (isset($show_columns['u']['saldo_date']))
	{
		if ($saldo_date)
		{
			$saldo_date_rev = $date_format->reverse($saldo_date);
		}

		if ($saldo_date_rev === false || $saldo_date == '')
		{
			$saldo_date = $date_format->get(false, 'day');

			array_walk($users, function(&$user, $user_id){
				$user['saldo_date'] = $user['saldo'];
			});
		}
		else
		{
			$in = $out = [];
			$datetime = new \DateTime($saldo_date_rev);

			$rs = $db->prepare('select id_to, sum(amount)
				from transactions
				where date <= ?
				group by id_to');

			$rs->bindValue(1, $datetime, 'datetime');

			$rs->execute();

			while($row = $rs->fetch())
			{
				$in[$row['id_to']] = $row['sum'];
			}

			$rs = $db->prepare('select id_from, sum(amount)
				from transactions
				where date <= ?
				group by id_from');
			$rs->bindValue(1, $datetime, 'datetime');

			$rs->execute();

			while($row = $rs->fetch())
			{
				$out[$row['id_from']] = $row['sum'];
			}

			array_walk($users, function(&$user) use ($out, $in){
				$user['saldo_date'] += $in[$user['id']];
				$user['saldo_date'] -= $out[$user['id']];
			});
		}
	}

	if (isset($show_columns['c']))
	{
		$c_ary = $db->fetchAll('SELECT tc.abbrev, c.id_user, c.value, c.flag_public
			FROM contact c, type_contact tc, users u
			WHERE tc.id = c.id_type_contact
				and c.id_user = u.id
				and ' . $st[$status]['sql'], $sql_bind);

		$contacts = [];

		foreach ($c_ary as $c)
		{
			$contacts[$c['id_user']][$c['abbrev']][] = [$c['value'], $c['flag_public']];
		}
	}

	if (isset($show_columns['m']))
	{
		$msgs_count = [];

		if (isset($show_columns['m']['offers']))
		{
			$ary = $db->fetchAll('select count(m.id), m.id_user
				from messages m, users u
				where msg_type = 1
					and m.id_user = u.id
					and ' . $st[$status]['sql'] . '
				group by m.id_user', $sql_bind);

			foreach ($ary as $a)
			{
				$msgs_count[$a['id_user']]['offers'] = $a['count'];
			}
		}

		if (isset($show_columns['m']['demands']))
		{
			$ary = $db->fetchAll('select count(m.id), m.id_user
				from messages m, users u
				where msg_type = 0
					and m.id_user = u.id
					and ' . $st[$status]['sql'] . '
				group by m.id_user', $sql_bind);

			foreach ($ary as $a)
			{
				$msgs_count[$a['id_user']]['demands'] = $a['count'];
			}
		}

		if (isset($show_columns['m']['total']))
		{
			$ary = $db->fetchAll('select count(m.id), m.id_user
				from messages m, users u
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
		$and = ' and u.letscode <> ? ';
		$sql_bind[] = trim($activity_filter_letscode);

		$in_ary = $db->fetchAll('select sum(t.amount), count(t.id), t.id_to
			from transactions t, users u
			where t.id_from = u.id
				and t.cdate > ?' . $and . '
			group by t.id_to', $sql_bind);

		$out_ary = $db->fetchAll('select sum(t.amount), count(t.id), t.id_from
			from transactions t, users u
			where t.id_to = u.id
				and t.cdate > ?' . $and . '
			group by t.id_from', $sql_bind);

		foreach ($in_ary as $in)
		{
			$activity[$in['id_to']]['trans_in'] = $in['count'];
			$activity[$in['id_to']]['amount_in'] = $in['sum'];
			$activity[$in['id_to']]['trans_total'] = $in['count'];
			$activity[$in['id_to']]['amount_total'] = $in['sum'];
		}

		foreach ($out_ary as $out)
		{
			$activity[$out['id_from']]['trans_out'] = $out['count'];
			$activity[$out['id_from']]['amount_out'] = $out['sum'];
			$activity[$out['id_from']]['trans_total'] += $out['count'];
			$activity[$out['id_from']]['amount_total'] += $out['sum'];
		}
	}
}
else
{
	$users = $db->fetchAll('select u.*
		from users u
		where ' . $st[$status]['sql'], $sql_bind);

	if ($v_list || $v_map)
	{
		$c_ary = $db->fetchAll('SELECT tc.abbrev, c.id_user, c.value, c.flag_public
			FROM contact c, type_contact tc
			WHERE tc.id = c.id_type_contact
				AND tc.abbrev IN (\'mail\', \'tel\', \'gsm\', \'adr\')');

		$contacts = [];

		foreach ($c_ary as $c)
		{
			$contacts[$c['id_user']][$c['abbrev']][] = [$c['value'], $c['flag_public']];
		}

		if (!$s_master)
		{
			if ($s_guest && $s_schema && !$s_elas_guest)
			{
				$my_adr = $db->fetchColumn('select c.value
					from ' . $s_schema . '.contact c, ' . $s_schema . '.type_contact tc
					where c.id_user = ?
						and c.id_type_contact = tc.id
						and tc.abbrev = \'adr\'', [$s_id]);
			}
			else if (!$s_guest)
			{
				$my_adr = $contacts[$s_id]['adr'][0][0];
			}

			$my_geo = false;

			if (isset($my_adr))
			{
				$geo = $redis->get('geo_' . $my_adr);

				if ($geo && $geo != 'q' && $geo != 'f')
				{
					$geo = json_decode($geo, true);
					$lat = $geo['lat'];
					$lng = $geo['lng'];
					$my_geo = true;
				}
			}
		}
	}
}

if ($s_admin)
{
	if ($v_list)
	{
		$top_right .= '<a href="#" class="csv">';
		$top_right .= '<i class="fa fa-file"></i>';
		$top_right .= '&nbsp;csv</a>&nbsp;';
	}

	$top_buttons .= aphp('users', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Gebruiker toevoegen', 'plus', true);

	if ($v_list)
	{
		$top_buttons .= '<a href="#actions" class="btn btn-default"';
		$top_buttons .= ' title="Bulk acties"><i class="fa fa-envelope-o"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Bulk acties</span></a>';
	}

	$h1 = 'Gebruikers';
}
else
{
	$h1 = 'Leden';
}

$h1 .= '<span class="pull-right hidden-xs">';
$h1 .= '<span class="btn-group" role="group">';

$active = ($v_list) ? ' active' : '';
$v_params = $params;
$v_params['view'] = 'list';
$h1 .= aphp('users', $v_params, '', 'btn btn-default' . $active, 'lijst', 'align-justify');

/*
$active = ($v_extended) ? ' active' : '';
$v_params = $params;
$v_params['view'] = 'extended';
$h1 .= aphp('users', $v_params, '', 'btn btn-default' . $active, 'lijst met omschrijvingen', 'th-list');
*/

$active = ($v_tiles) ? ' active' : '';
$v_params['view'] = 'tiles';
$h1 .= aphp('users', $v_params, '', 'btn btn-default' . $active, 'tegels met foto\'s', 'th');

$active = ($v_map) ? ' active' : '';
$v_params['view'] = 'map';
unset($v_params['status']);
$h1 .= aphp('users', $v_params, '', 'btn btn-default' . $active, 'kaart', 'map-marker');

$h1 .= '</span>';

if ($s_admin && $v_list)
{
	$h1 .= '&nbsp;<button class="btn btn-info" title="Toon kolommen" ';
	$h1 .= 'data-toggle="collapse" data-target="#columns_show"';
	$h1 .= '><i class="fa fa-columns"></i></button>';
}

$h1 .= '</span>';

if (($s_user || $s_admin) && !$s_master)
{
	$top_buttons .= aphp('users', ['id' => $s_id], 'Mijn gegevens', 'btn btn-default', 'Mijn gegevens', 'user', true);
}

$fa = 'users';

if ($v_list)
{
	$include_ary[] = 'calc_sum.js';
	$include_ary[] = 'users_distance.js';

	if ($s_admin)
	{
		$include_ary[] = 'datepicker';
		$include_ary[] = 'csv.js';
		$include_ary[] = 'table_sel.js';
	}
}
else if ($v_tiles)
{
	$include_ary[] = 'isotope';
	$include_ary[] = 'users_tiles.js';
}
else if ($v_map)
{
	$include_ary[] = 'leaflet';
	$include_ary[] = 'leaflet_label';
	$include_ary[] = 'users_map.js';
}

include $rootpath . 'includes/inc_header.php';

if ($v_map)
{
	$data_users = [];
	$hidden_count = $not_geocoded_count = $not_preset_count = 0;

	foreach ($users as $user)
	{
		$adr = $contacts[$user['id']]['adr'][0];

		if ($adr)
		{

			$geo = json_decode($redis->get('geo_' . $adr[0]), true);

			if ($adr[1] >= $access_level)
			{
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
		}
	}

	$shown_count = count($data_users);

	if (!($lat && $lng) && $shown_count)
	{
		$lat = $lat_add / $shown_count;
		$lng = $lng_add / $shown_count;
	}

	$data_users = json_encode($data_users);

	echo '<div class="row">';
	echo '<div class="col-md-12">';
	echo '<div class="users_map" id="map" data-users="' . htmlspecialchars($data_users) . '" ';
	echo 'data-lat="' . $lat . '" data-lng="' . $lng . '" data-token="' . $mapbox_token . '" ';
	echo 'data-session-param="' . get_session_query_param() . '"></div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading"><p>';

	if ($hidden_count || $not_present_count || $not_geocoded_count)
	{

		echo ($hidden_count + $not_present_count + $not_geocoded_count) . ' ';
		echo ($s_admin) ? 'gebruikers' : 'leden';
		echo ' worden niet getoond in de kaart wegens: ';
		echo '<ul>';
		echo ($hidden_count) ? '<li>' . $hidden_count . ' verborgen adres</li>' : '';
		echo ($not_present_count) ? '<li>' . $not_present_count . ' geen adres gekend</li>' : '';
		echo ($not_geocoded_count) ? '<li>' . $not_geocoded_count . ' coordinaten nog niet opgezocht voor adres.</li>' : '';
		echo '</ul>';
		if ($not_geocoded_count)
		{
			echo 'Wanneer een adres aangepast is of net toegevoegd, duurt het enige tijd eer de coordinaten zijn opgezocht door de software ';
			echo '(maximum één dag).';
		}
	}

	echo '</p></div>';
	echo '</div>';
}

if ($v_list || $v_extended || $v_tiles)
{
	echo '<form method="get" action="' . generate_url('users', $params) . '">';

	$params_plus = array_merge($params, get_session_query_param());

	foreach ($params_plus as $k => $v)
	{
		echo '<input type="hidden" name="' . $k . '" value="' . $v . '">';
	}
}

if ($s_admin && $v_list)
{
	echo '<div class="panel panel-info collapse" id="columns_show">';
	echo '<div class="panel-heading">';
	echo '<h3>Toon kolommen</h3>';
	echo '</div>';
	echo '<div class="panel-body">';

	foreach ($columns as $group => $ary)
	{
		if ($group == 'c')
		{
			echo '<h3>Contacten</h3>';
		}
		else if ($group == 'a')
		{
			echo '<h3>Transacties/activiteit</h3>';
			echo '<p>In de laatste <input type="number" name="activity_days" value="' . $activity_days . '" ';
			echo 'size="4" min="1"> dagen. Exclusief tegenpartij (letscode): <input type="text" name="activity_filter_letscode" ';
			echo 'value="' . $activity_filter_letscode . '"></p>';
		}
		else if ($group == 'm')
		{
			echo '<h3>Vraag en aanbod</h3>';
		}

		foreach ($ary as $key => $lbl)
		{
			echo '<div class="checkbox">';
			echo '<label>';
			echo '<input type="checkbox" name="sh[' . $group . '][' . $key . ']" value="1"';
			echo (isset($show_columns[$group][$key])) ? ' checked="checked"' : '';
			echo '> ' . $lbl;
			echo ($key == 'adr') ? ', split door teken: <input type="text" name="adr_split" size="1" value="' . $adr_split . '">' : '';

			if ($key == 'saldo_date')
			{
				echo '<input type="text" name="saldo_date" ';
				echo 'data-provide="datepicker" ';
				echo 'data-date-format="' . $date_format->datepicker_format() . '" ';
				echo 'data-date-language="nl" ';
				echo 'data-date-today-highlight="true" ';
				echo 'data-date-autoclose="true" ';
				echo 'data-date-enable-on-readonly="false" ';
				echo 'data-date-end-date="0d" ';
				echo 'data-date-orientation="bottom" ';
				echo 'placeholder="' . $date_format->datepicker_placeholder() . '" ';
				echo 'value="' . $saldo_date . '">';

				$columns['u']['saldo_date'] = 'Saldo op ' . $saldo_date;
			}

			echo '</label>';
			echo '</div>';
		}
	}

	echo '</div>';
	echo '<div class="panel-footer">';
	echo '<input type="submit" name="show" class="btn btn-default" value="Toon">';
	echo '</div>';
	echo '</div>';
}

if ($v_list || $v_extended || $v_tiles)
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
	echo '<input type="text" class="form-control" id="q" name="q" value="' . $q . '" ';
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
		echo ($status == $k) ? ' class="active"' : '';
		echo '>';
		$class = (isset($tab['cl'])) ? 'bg-' . $tab['cl'] : false;
		echo aphp('users', $nav_params, $tab['lbl'], $class) . '</li>';
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
	echo 'data-empty="Er zijn geen ' . (($s_admin) ? 'gebruikers' : 'leden') . ' volgens ';
	echo 'de selectiecriteria" data-sorting="true" data-filter-placeholder="Zoeken" ';
	echo 'data-filter-position="left"';

	if (isset($my_geo))
	{
		echo ' data-lat="' . $lat . '" data-lng="' . $lng . '"';
	}

	echo '>';
	echo '<thead>';

	echo '<tr>';

	if ($s_admin)
	{
		foreach ($show_columns as $group => $ary)
		{
			$data_sort_ignore = ($group == 'c') ? ' data-sort-ignore="true"' : '';
			$data_type = ($group == 'a' || $group == 'm') ? ' data-type="numeric"' : '';

			foreach ($ary as $key => $one)
			{
				if ($key == 'adr' && $adr_split != '')
				{
					echo '<th data-sort-ignore="true">Adres (1)</th>';
					echo '<th data-sort-ignore="true">Adres (2)</th>';
					continue;
				}

				$data_type = ($key == 'saldo') ? ' data-type="numeric"' : $data_type;
				$sort_initial = (isset($sort_initial)) ? '' : ' data-sort-initial="true"';

				echo '<th' . $sort_initial . $data_sort_ignore . $data_type . '>' . $columns[$group][$key] . '</th>';
			}
		}

		echo '</tr>';

		echo '</thead>';
		echo '<tbody>';

		foreach($users as $u)
		{
			$id = $u['id'];

			$row_stat = ($u['status'] == 1 && $newusertreshold < strtotime($u['adate'])) ? 3 : $u['status'];

			$class = (isset($st_class_ary[$row_stat])) ? ' class="' . $st_class_ary[$row_stat] . '"' : '';

			$checkbox = '<input type="checkbox" name="sel_' . $id . '" value="1"';
			$checkbox .= (isset($selected_users[$id])) ? ' checked="checked"' : '';
			$checkbox .= '>&nbsp;';

			$first = true;

			echo '<tr' . $class . ' data-balance="' . $u['saldo'] . '">';

			if (isset($show_columns['u']))
			{
				foreach ($show_columns['u'] as $key => $one)
				{
					echo '<td>';
					echo ($first) ? $checkbox : '';
					$first = false;
					echo ($key == 'letscode' || $key == 'name' || $key == 'fullname') ? link_user($u, false, true, false, $key) : $u[$key];
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
						list($adr_1, $adr_2) = explode(trim($adr_split), $contacts[$id]['adr'][0][0]);
						echo $adr_1;
						echo '</td><td>';
						echo $adr_2;
					}
					else
					{
						echo render_contacts($contacts[$id][$key], $key);
					}
					echo '</td>';
				}
			}

			if (isset($show_columns['m']))
			{
				foreach($show_columns['m'] as $key => $one)
				{
					echo '<td>';
					echo $msgs_count[$id][$key];
					echo '</td>';
				}
			}

			if (isset($show_columns['a']))
			{
				foreach($show_columns['a'] as $key => $one)
				{
					echo '<td>';
					echo $activity[$id][$key];
					echo '</td>';
				}
			}

			echo '</tr>';
		}
	}
	else
	{
		echo '<th data-sort-initial="true">Code</th>';
		echo '<th>Naam</th>';
		echo '<th data-hide="tablet, phone" data-sort-ignore="true">Tel</th>';
		echo '<th data-hide="tablet, phone" data-sort-ignore="true">gsm</th>';
		echo '<th data-hide="phone">Postcode</th>';
		echo ($my_geo) ? '<th data-hide="phone" data-type="numeric">Afstand</th>' : '';
		echo '<th data-hide="tablet, phone" data-sort-ignore="true">Mail</th>';
		echo '<th data-hide="phone">Saldo</th>';

		echo '</tr>';

		echo '</thead>';
		echo '<tbody>';

		foreach($users as $u)
		{
			$id = $u['id'];
			$adr_ary = $contacts[$id]['adr'][0];

			$row_stat = ($u['status'] == 1 && $newusertreshold < strtotime($u['adate'])) ? 3 : $u['status'];
			$class = (isset($st_class_ary[$row_stat])) ? ' class="' . $st_class_ary[$row_stat] . '"' : '';

			$balance = $u['saldo'];
			$balance_class = ($balance < $u['minlimit'] || $balance > $u['maxlimit']) ? ' class="text-danger"' : '';

			echo '<tr' . $class . ' data-balance="' . $u['saldo'] . '"';

			echo '>';
			echo '<td>' . link_user($u, false, true, false, 'letscode') . '</td>';
			echo '<td>' . link_user($u, false, true, false, 'name') . '</td>';
			echo '<td>' . render_contacts($contacts[$id]['tel']) . '</td>';
			echo '<td>' . render_contacts($contacts[$id]['gsm']) . '</td>';
			echo '<td>' . $u['postcode'] . '</td>';
			if ($my_geo)
			{
				echo '<td data-value="5000"';
				if ($adr_ary && $adr_ary[0] && $adr_ary[1] >= $access_level)
				{
					$geo = json_decode($redis->get('geo_' . $adr_ary[0]), true);

					if ($geo && $geo != 'q' && $geo != 'f')
					{
						echo ' data-lat="' . $geo['lat'] . '" data-lng="' . $geo['lng'] . '"';
					}
				}
				echo '><i class="fa fa-times"></i></td>';
			}
			echo '<td>' . render_contacts($contacts[$id]['mail'], 'mail') . '</td>';
			echo '<td><span class="' . $balance_class  . '">' . $balance . '</span></td>';
			echo '</tr>';
		}
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div></div>';

	echo '<div class="row"><div class="col-md-12">';
	echo '<p><span class="pull-right">Totaal saldo: <span id="sum"></span> ' . $currency . '</span></p>';
	echo '</div></div>';

/*
	echo '<ul>';
	if ($s_user)
	{
		echo '<li>Je kan enkel de afstand tot andere leden waarvan de zichtbaarheid van het adres ';
		echo 'staat ingesteld op <span class="label label-warning">leden</span> of ';
		echo '<span class="label label-success">interlets</span> en als je zelf je adres in je contacten hebt staan.';
		echo '</li>';
	}
	echo '</ul>';
*/

	if ($s_admin & isset($show_columns['u']))
	{
		$form_token = generate_form_token(false);
		$pw_name_suffix = substr($form_token, 0, 5);

		$inp =  '<div class="form-group">';
		$inp .=  '<label for="%5$s" class="col-sm-2 control-label">%2$s</label>';
		$inp .=  '<div class="col-sm-10">';
		$inp .=  '<input type="%3$s" id="%5$s" name="%1$s" %4$s>';
		$inp .=  '</div>';
		$inp .=  '</div>';

		$acc_sel = '<div class="form-group">';
		$acc_sel .= '<label for="%1$s" class="col-sm-2 control-label">';
		$acc_sel .= '%2$s</label>';
		$acc_sel .= '<div class="col-sm-10">';
		$acc_sel .= '<select name="%1$s" id="%1$s" class="form-control">';
		$acc_sel .= '%3$s';
		$acc_sel .= '</select>';
		$acc_sel .= '</div>';
		$acc_sel .= '</div>';

		echo '<div class="panel panel-default" id="actions">';
		echo '<div class="panel-heading">';
		echo '<span class="btn btn-default" id="select_all">Selecteer alle</span>&nbsp;';
		echo '<span class="btn btn-default" id="deselect_all">De-selecteer alle</span>';
		echo '</div></div>';
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
			echo '<li><a href="#' . $k . '_tab" data-toggle="tab">' . $t['lbl'] . '</a></li>';
		}

		echo '</ul>';
		echo '</li>';
		echo '</ul>';

		echo '<div class="tab-content">';

		echo '<div role="tabpanel" class="tab-pane active" id="mail_tab">';
		echo '<h3>Mail verzenden naar geselecteerde gebruikers</h3>';

		echo '<form method="post" class="form-horizontal">';

		echo '<div class="form-group">';
		echo '<div class="col-sm-12">';
		echo '<input type="text" class="form-control" id="bulk_mail_subject" name="bulk_mail_subject" ';
		echo 'placeholder="Onderwerp" ';
		echo 'value="';
		echo isset($bulk_mail_subject) ? $bulk_mail_subject : '';
		echo '" required>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<div class="col-sm-12">';
		echo '<textarea name="bulk_mail_content" class="form-control" id="bulk_mail_content" rows="16" ';
		echo 'required>';
		echo isset($bulk_mail_content) ? $bulk_mail_content : '';
		echo '</textarea>';
		echo '</div>';
		echo '</div>';

		echo sprintf($inp, 'mail_password_' . $pw_name_suffix,
			'Je paswoord (extra veiligheid)', 'password', 'class="form-control" required', 'mail_password');

		echo '<input type="submit" value="Zend test mail naar jezelf" name="bulk_mail_test" class="btn btn-default">&nbsp;';
		echo '<input type="submit" value="Verzend" name="bulk_mail_submit" class="btn btn-default">';
		echo '<p data-toggle="collapse" data-target="#mail_variables" style="cursor: pointer">';
		echo 'Klik hier om variabelen te zien die in een mail gebruikt kunnen worden.</p>';
		echo '<div class="table-responsive collapse" id="mail_variables">';
		echo '<table class="table table-bordered table-hover" data-sort="false">';

		echo '<tbody>';
		echo '<tr><td>{{letscode}}</td><td>Letscode</td></tr>';
		echo '<tr><td>{{naam}}</td><td>Gebruikersnaam</td></tr>';
		echo '<tr><td>{{volledige_naam}}</td><td>Volledige naam (Voornaam + Achternaam)</td></tr>';
		echo '<tr><td>{{postcode}}</td><td>Postcode</td></tr>';
		echo '<tr><td>{{status}}</td><td>Status</td></tr>';
		echo '<tr><td>{{min_limiet}}</td><td>Minimum limiet</td></tr>';
		echo '<tr><td>{{max_limiet}}</td><td>Maximum limiet</td></tr>';
		echo '<tr><td>{{saldo}}</td><td>Huidig saldo in ' . $currency . '</td></tr>';
		echo '<tr><td>{{id}}</td><td>Gebruikers id (kan gebruikt worden om urls te vormen).</td></tr>';
		echo '</body>';
		echo '</table>';

		echo '</div>';
		echo '</div>';
		generate_form_token();
		echo '</form>';

		foreach($edit_fields_tabs as $k => $t)
		{
			echo '<div role="tabpanel" class="tab-pane" id="' . $k . '_tab"';
			echo (isset($t['access_control'])) ? ' data-access-control="true"' : '';
			echo '>';
			echo '<h3>Veld aanpassen: ' . $t['lbl'] . '</h3>';

			echo '<form method="post" class="form-horizontal">';

			if (isset($t['options']))
			{
				$options = $t['options'];
				echo sprintf($acc_sel, $k, $t['lbl'], render_select_options($$options, 0, false));
			}
			else if (isset($t['type']) && $t['type'] == 'checkbox')
			{
				echo sprintf($inp, $k, $t['lbl'], $t['type'], 'value="1"', $k);
			}
			else if (isset($t['access_control']))
			{
				echo $access_control->get_radio_buttons();
			}
			else
			{
				echo sprintf($inp, $k, $t['lbl'], $t['type'], 'class="form-control"', $k);
			}

			echo sprintf($inp, $k . '_password_' . $pw_name_suffix,
				'Paswoord', 'password', 'class="form-control" required', $k . '_password');

			echo '<input type="submit" value="Veld aanpassen" name="' . $k . '_bulk_submit" class="btn btn-primary">';
			generate_form_token();
			echo '</form>';

			echo '</div>';
		}

		echo '<div class="clearfix"></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
}
else if ($v_extended)
{
	foreach ($users as $u)
	{
		$row_stat = ($u['status'] == 1 && $newusertreshold < strtotime($u['adate'])) ? 3 : $u['status'];

		$class = (isset($st_class_ary[$row_stat])) ? ' bg-' . $st_class_ary[$row_stat] : '';

		echo '<div class="panel panel-info printview">';
		echo '<div class="panel-body';
		echo $class;
		echo '">';

		echo '<div class="media">';

		if ($u['PictureFile'])
		{
			echo '<div class="media-left">';
			echo '<a href="' . generate_url('users', ['id' => $u['id']]) . '">';
			echo '<img class="media-object" src="' . $s3_img_url . $u['PictureFile'] . '" width="150">';
			echo '</a>';
			echo '</div>';
		}
		echo '<div class="media-body">';

		echo '<h3 class="media-heading">';
		echo link_user($u);
		echo '</h3>';

		echo htmlspecialchars($u['hobbies'], ENT_QUOTES);
		echo htmlspecialchars($u['postcode'], ENT_QUOTES);
		echo '</div>';
		echo '</div>';


		echo '</div>';

		echo '<div class="panel-footer">';
		echo '<p><i class="fa fa-user"></i>' . link_user($msg['id_user']);
		echo ($msg['postcode']) ? ', postcode: ' . $u['postcode'] : '';

		if ($s_admin)
		{
			echo '<span class="inline-buttons pull-right">';
			echo aphp('users', ['edit' => $u['id']], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
			echo aphp('users', ['del' => $u['id']], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
			echo '</span>';
		}
		echo '</p>';
		echo '</div>';

		echo '</div>';
	}
}
else if ($v_tiles)
{
	echo '<p>';
	echo '<span class="btn-group sort-by" role="group">';
	echo '<button class="btn btn-default active" data-sort-by="letscode">letscode ';
	echo '<i class="fa fa-sort-asc"></i></button>';
	echo '<button class="btn btn-default" data-sort-by="name">naam ';
	echo '<i class="fa fa-sort"></i></button>';
	echo '<button class="btn btn-default" data-sort-by="postcode">postcode ';
	echo '<i class="fa fa-sort"></i></button>';
	echo '</span>';
	echo '</p>';

	echo '<div class="row tiles">';

	foreach ($users as $u)
	{
		$row_stat = ($u['status'] == 1 && $newusertreshold < strtotime($u['adate'])) ? 3 : $u['status'];
		$class = $st_class_ary[$row_stat];
		$class = (isset($class)) ? ' class="bg-' . $class . '"' : '';

		$url = generate_url('users', ['id' => $u['id']]);
		echo '<div class="col-xs-4 col-md-3 col-lg-2 tile">';
		echo '<div' . $class . '>';
		echo '<div class="thumbnail text-center">';
		echo '<a href="' . $url . '">';

		if (isset($u['PictureFile']) && $u['PictureFile'] != '')
		{
			echo '<img src="' . $s3_img_url . $u['PictureFile'] . '" class="img-rounded">';
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

include $rootpath . 'includes/inc_footer.php';

function render_contacts($contacts, $abbrev = null)
{
	global $access_level;

	$ret = '';

	if (count($contacts))
	{
		end($contacts);
		$end = key($contacts);

		$f = ($abbrev == 'mail') ? '<a href="mailto:%1$s">%1$s</a>' : '%1$s';

		foreach ($contacts as $key => $contact)
		{
			if ($contact[1] >= $access_level)
			{
				$ret .= sprintf($f, htmlspecialchars($contact[0], ENT_QUOTES));

				if ($key == $end)
				{
					break;
				}
				$ret .= ',<br>';
			}
		}
	}
	else
	{
		$ret .= '&nbsp;';
	}

	return $ret;
}

function cancel($id = null)
{
	global $view_users;

	$params = [];

	if ($id)
	{
		$params['id'] = $id;
	}
	else
	{
		$params['view'] = $view_users;
		$params['status'] = 'active';
	}	

	header('Location: ' . generate_url('users', $params));
	exit;
}

function dd_render($str)
{
	echo '<dd>';
	echo ($str) ? htmlspecialchars($str, ENT_QUOTES) : '<span class="fa fa-times"></span>';
	echo '</dd>';
}

function sendadminmail($user)
{
	global $systemtag;

	$subject .= 'Account activatie';

	$text  = "*** Dit is een automatische mail van ";
	$text .= $systemtag;
	$text .= " ***\r\n\n";
	$text .= "De account " . link_user($user, false, false) ;
	$text .= " werd geactiveerd met een nieuw paswoord.\n";

	if ($user['mail'])
	{
		$text .= 'Er werd een mail verstuurd naar de gebruiker.';
		$text .= ".\n\n";
	}
	else
	{
		$text .= "Er werd GEEN mail verstuurd omdat er geen E-mail adres bekend is voor de gebruiker.\n\n";
	}

	$text .= "OPMERKING: Vergeet niet om de gebruiker eventueel toe te voegen aan andere LETS programma's zoals mailing lists.\n\n";

	mail_q(['to' => 'admin', 'subject' => $subject, 'text' => $text]);
}

function sendactivationmail($password, $user)
{
	global $base_url, $alert, $systemname, $systemtag;

	if (empty($user['mail']))
	{
		$alert->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het wachtwoord op een andere manier door!');
		return 0;
	}

	$subject = 'account activatie voor ' . $systemname;

	$text  = "*** Dit is een automatische mail van ";
	$text .= $systemname;
	$text .= " ***\r\n\n";
	$text .= 'Beste ';
	$text .= $user['name'];
	$text .= "\n\n";

	$text .= "Welkom bij Letsgroep $systemname";
	$text .= '. Surf naar ' . $base_url;
	$text .= " en meld je aan met onderstaande gegevens.\n";
	$text .= "\n-- Account gegevens --\n";
	$text .= "Login: ";
	$text .= $user['letscode']; 
	$text .= "\nPasswoord: ";
	$text .= $password;
	$text .= "\n-- --\n\n";

	$text .= "Je kan je gebruikersgevens, vraag&aanbod en lets-transacties";
	$text .= " zelf bijwerken op het Internet.";
	$text .= "\n\n";

	$text .= "Als je nog vragen of problemen hebt, kan je terecht bij ";
	$text .= readconfigfromdb('support');
	$text .= "\n\n";
	$text .= "Veel plezier bij het letsen! \n";

	mail_q(['to' => $user['id'], 'subject' => $subject, 'text' => $text, 'reply_to' => 'support']);
}
