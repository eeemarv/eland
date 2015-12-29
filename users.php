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
$user_mail_submit = ($_POST['user_mail_submit']) ? true : false;

$inline = (isset($_GET['inline'])) ? true : false;

$q = (isset($_GET['q'])) ? $_GET['q'] : '';

$role = ($edit || $pw || $img_del || $password || $submit || $img) ? 'user' : 'guest';
$role = ($add || $del) ? 'admin' : $role;
$allow_guest_post = ($role == 'guest' && $user_mail_submit) ? true : false;

if (!$inline)
{
	require_once $rootpath . 'includes/inc_passwords.php';
}

require_once $rootpath . 'includes/inc_default.php';

/**
 * mail to user
 */
if ($user_mail_submit && $id && $post)
{
	$content = $_POST['content'];
	$cc = $_POST['cc'];

	$user = readuser($id);

	$to = $db->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', array($id));

	if (isset($s_interlets['schema']))
	{
		$t_schema =  $s_interlets['schema'] . '.';
		$remote_schema = $s_interlets['schema'];
		$me_id = $s_interlets['id'];
	}
	else
	{
		$t_schema = '';
		$remote_schema = false;
		$me_id = $s_id;
	}

	$me = readuser($me_id, false, $remote_schema);

	$user_me = (isset($s_interlets['schema'])) ? readconfigfromdb('systemtag', $remote_schema) . '.' : '';
	$user_me .= link_user($me, null, false);
	$user_me .= (isset($s_interlets['schema'])) ? ' van interlets groep ' . readconfigfromdb('systemname', $remote_schema) : '';

	$from = $db->fetchColumn('select c.value
		from ' . $t_schema . 'contact c, ' . $t_schema . 'type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', array($me_id));

	$my_contacts = $db->fetchAll('select c.value, tc.abbrev
		from ' . $t_schema . 'contact c, ' . $t_schema . 'type_contact tc
		where c.flag_public >= ?
			and c.id_user = ?
			and c.id_type_contact = tc.id', array($access_ary[$user['accountrole']], $me_id));

	$subject = '[' . $systemtag . '] - Bericht van ' . $systemname;

	$mailcontent = 'Beste ' . $user['name'] . "\r\n\r\n";
	$mailcontent .= 'Gebruiker ' . $user_me . " heeft een bericht naar je verstuurd via de webtoepassing\r\n\r\n";
	$mailcontent .= '--------------------bericht--------------------' . "\r\n\r\n";
	$mailcontent .= $content . "\r\n\r\n";
	$mailcontent .= '-----------------------------------------------' . "\r\n\r\n";
	$mailcontent .= "Om te antwoorden kan je gewoon reply kiezen of de contactgegevens hieronder gebruiken\r\n\r\n";
	$mailcontent .= 'Contactgegevens van ' . $user_me . ":\r\n\r\n";

	foreach($my_contacts as $value)
	{
		$mailcontent .= '* ' . $value['abbrev'] . "\t" . $value['value'] ."\n";
	}

	if ($content)
	{
		if ($cc)
		{
			$msg = 'Dit is een kopie van het bericht dat je naar ' . $user['letscode'] . ' ';
			$msg .= $user['name'];
			$msg .= ($s_interlets) ? ' van letsgroep ' . $systemname : '';
			$msg .= ' verzonden hebt. ';
			$msg .= "\r\n\r\n\r\n";
			$mail_status = sendemail($from, $from, $subject . ' (kopie)', $msg . $mailcontent);
		}

		$mailcontent .= "\r\n\r\nInloggen op de website: " . $base_url . "\r\n\r\n";

		if (!$mail_status)
		{
			$mail_status = sendemail($from, $to, $subject, $mailcontent);
		}

		if ($mail_status)
		{
			$alert->error($mail_status);
		}
		else
		{
			$alert->success('Mail verzonden.');
		}
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
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));
}

/*
 * upload image
 */

if ($post && $img && $id )
{
	$s_owner = ($s_id == $id) ? true : false;

	if (!($s_owner || $s_admin))
	{
		echo json_encode(array('error' => 'Je hebt onvoldoende rechten voor deze actie.'));
		exit;
	}

	$user = readuser($id);

	$image = ($_FILES['image']) ?: null;

	if (!$image)
	{
		echo json_encode(array('error' => 'Afbeeldingsbestand ontbreekt.'));
		exit;
	}

	$size = $image['size'];
	$tmp_name = $image['tmp_name'];
	$type = $image['type'];

	if ($size > 200 * 1024)
	{
		echo json_encode(array('error' => 'Het bestand is te groot.'));
		exit;
	}

	if ($type != 'image/jpeg')
	{
		echo json_encode(array('error' => 'Ongeldig bestandstype.'));
		exit;
	}

	try {

		if ($user['PictureFile'])
		{
			$s3->deleteObject(array(
				'Bucket'	=> $s3_img,
				'Key'		=> $user['PictureFile'],
			));
		}

		$filename = $schema . '_u_' . $id . '_' . sha1(time()) . '.jpg';

		$upload = $s3->upload($s3_img, $filename, fopen($tmp_name, 'rb'), 'public-read', array(
			'params'	=> array(
				'CacheControl'	=> 'public, max-age=31536000',
				'ContentType'	=> 'image/jpeg',
			),
		));

		$db->update('users', array(
			'"PictureFile"'	=> $filename
		),array('id' => $id));

		log_event($s_id, 'Pict', 'User image ' . $filename . ' uploaded. User: ' . $id);

		readuser($id, true);

		unlink($tmp_name);
	}
	catch(Exception $e)
	{
		echo json_encode(array('error' => $e->getMessage()));
		log_event($s_id, 'Pict', 'Upload fail : ' . $e->getMessage());
		exit;
	}

	header('Pragma: no-cache');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Content-Disposition: inline; filename="files.json"');
	header('X-Content-Type-Options: nosniff');
	header('Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size');

	header('Vary: Accept');

	echo json_encode(array('success' => 1, 'filename' => $filename));
	exit;
}

/**
 * delete image
 */
if ($img_del && $id)
{
	$s_owner = ($s_id == $id) ? true : false;

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
		$s3->deleteObject(array(
			'Bucket'	=> $s3_img,
			'Key'		=> $file,
		));

		$db->update('users', array('"PictureFile"' => ''), array('id' => $id));
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

	echo aphp('users', 'id=' . $id, 'Annuleren', 'btn btn-default'). '&nbsp;';
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
if ($s_admin)
{
	$edit_fields_tabs = array(
		'fullname_access'	=> array(
			'lbl'		=> 'Zichtbaarheid volledige naam',
			'options'	=> 'access_options',
		),
		'adr_access'		=> array(
			'lbl'		=> 'Zichtbaarheid adres',
			'options'	=> 'access_options',
		),
		'mail_access'		=> array(
			'lbl'		=> 'Zichtbaarheid email adres',
			'options'	=> 'access_options',
		),
		'tel_access'		=> array(
			'lbl'		=> 'Zichtbaarheid telefoonnummer',
			'options'	=> 'access_options',
		),
		'gsm_access'		=> array(
			'lbl'		=> 'Zichtbaarheid gsmnummer',
			'options'	=> 'access_options',
		),
		'comments'			=> array(
			'lbl'		=> 'Commentaar',
			'type'		=> 'text',
			'string'	=> true,
		),
		'accountrole'		=> array(
			'lbl'		=> 'Rechten',
			'options'	=> 'role_ary',
			'string'	=> true,
		),
		'status'			=> array(
			'lbl'		=> 'Status',
			'options'	=> 'status_ary',
		),
		'admincomment'		=> array(
			'lbl'		=> 'Commentaar van de admin',
			'type'		=> 'text',
			'string'	=> true,
		),
		'minlimit'			=> array(
			'lbl'		=> 'Minimum limiet saldo',
			'type'		=> 'number',
		),
		'maxlimit'			=> array(
			'lbl'		=> 'Maximum limiet saldo',
			'type'		=> 'number',
		),
		'cron_saldo'		=> array(
			'lbl'	=> 'Periodieke mail met recent vraag en aanbod (aan/uit)',
			'type'	=> 'checkbox',
		),
	);
}

if ($post && $s_admin)
{
	$field_submit = false;

	$mail_submit = $_POST['mail_submit'];
	$mail_test = $_POST['mail_test'];

	$selected_users = $_POST['sel'];

	if (!($mail_test || $mail_submit))
	{
		foreach ($edit_fields_tabs as $field => $t)
		{
			if (isset($_POST[$field . '_submit']))
			{
				$field_submit = true;
				break;
			}
		}
	}

	if ($field_submit || $mail_submit)
	{
		$password = ($mail_submit) ? $_POST['mail_password'] : $_POST[$field . '_password'];
		$value = $_POST[$field];

		$errors = array();

		if (!$password)
		{
			$errors[] = 'Vul je paswoord in.';
		}
		$password = hash('sha512', $password);

		if ($password != $db->fetchColumn('select password from users where id = ?', array($s_id)))
		{
			$errors[] = 'Paswoord is niet juist.';
		}
	}

	if ($mail_test || $mail_submit)
	{
		$mail_subject = $_POST['mail_subject'];
		$mail_content = $_POST['mail_content'];

		if (!$mail_subject)
		{
			$errors[] = 'Gelieve een onderwerp in te vullen voor je mail.';
		}
		if (!$mail_content)
		{
			$errors[] = 'Het mail bericht is leeg.';
		}
		if (!readconfigfromdb('mailenabled'))
		{
			$errors[] = 'Mail functies zijn niet ingeschakeld. Zie instellingen.';
		}
	}
}

if ($s_admin && ($field_submit || $mail_test || $mail_submit) && $post)
{
	if (!count($selected_users) && !$mail_test)
	{
		$errors[] = 'Selecteer ten minste één gebruiker voor deze actie.';
	}

	if (count($errors))
	{
		$alert->error(implode('<br>', $errors));
	}
	else
	{
		$user_ids = array_keys($selected_users);
	}
}

/**
 * change a field for multiple users
 */
if ($s_admin && !count($errors) && $field_submit && $post)
{
	$users_log = '';
	$rows = $db->executeQuery('select letscode, name, id from users where id in (?)',
			array($user_ids), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
	foreach ($rows as $row)
	{
		$users_log .= ', ' . link_user($row, null, false, true);
	}
	$users_log = ltrim($users_log, ', ');

	if ($field == 'fullname_access')
	{
		$mdb->connect();

		foreach ($user_ids as $user_id)
		{
			$mdb->users->update(
				array('id' => (int) $user_id),
				array('$set' => array('id' => (int) $user_id, 'fullname_access' => (int) $value)),
				array('upsert' => true)
			);

			$redis->del($schema . '_user_' . $user_id);
		}

		log_event($s_id, 'bulk', 'Set fullname_access to ' . $value . ' for users ' . $users_log);
		$alert->success('De zichtbaarheid van de volledige naam werd aangepast.');
		cancel();
	}
	else if (array('cron_saldo' => 1, 'accountrole' => 1, 'status' => 1, 'comments' => 1,
		'admincomment' => 1, 'minlimit' => 1, 'maxlimit' => 1)[$field])
	{
		$type = ($edit_fields_tabs[$field]['string']) ? \PDO::PARAM_STR : \PDO::PARAM_INT;

		$db->executeUpdate('update users set ' . $field . ' = ? where id in (?)',
			array($value, $user_ids),
			array($type, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY));

		foreach ($user_ids as $user_id)
		{
			$redis->del($schema . '_user_' . $user_id);
		}

		log_event($s_id, 'bulk', 'Set ' . $field . ' to ' . $value . ' for users ' . $users_log);
		$alert->success('Het veld werd aangepast.');
		cancel();
	}
	else if (array('adr_access' => 1, 'mail_access' => 1, 'tel_access' => 1, 'gsm_access' => 1)[$field])
	{
		list($abbrev) = explode('_', $field);

		$id_type_contact = $db->fetchColumn('select id from type_contact where abbrev = ?', array($abbrev));

		$db->executeUpdate('update contact set flag_public = ? where id_user in (?) and id_type_contact = ?',
			array($value, $user_ids, $id_type_contact),
			array(\PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY, \PDO::PARAM_INT));

		log_event($s_id, 'bulk', 'Set ' . $field . ' to ' . $value . ' for users ' . $users_log);
		$alert->success('Het veld werd aangepast.');
		cancel();
	}
}

if ($s_admin && !count($errors) && ($mail_submit || $mail_test) && $post)
{
	$to = $merge_vars = array();
	$to_log = '';

	$sel_ary = ($mail_test) ? array($s_id => true) : $selected_users;

	$st = $db->prepare('select u.*, c.value as mail
		from users u, contact c, type_contact tc
		where u.id = c.id_user
			and c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'');

	$st->execute();

	while ($user = $st->fetch())
	{
		if ($user['id'] == $s_id)
		{
			$from = $user['mail'];
		}

		if (!$sel_ary[$user['id']])
		{
			continue;
		}

		$to_log .= ', ' . $user['letscode'] . ' ' . $user['name'] . ' (' . $user['id'] . ')';

		$to[] = array(
			'email'	=> $user['mail'],
			'name'	=> $user['name'],
		);
		$merge_vars[] = array(
			'rcpt'	=> $user['mail'],
			'vars'	=> array(
				array(
					'name'		=> 'naam',
					'content'	=> $user['name'],
				),
				array(
					'name'		=> 'volledige_naam',
					'content'	=> $user['fullname'],
				),
				array(
					'name'		=> 'saldo',
					'content'	=> $user['saldo'],
				),
				array(
					'name'		=> 'letscode',
					'content'	=> $user['letscode'],
				),
				array(
					'name'		=> 'postcode',
					'content'	=> $user['postcode'],
				),
				array(
					'name'		=> 'id',
					'content'	=> $user['id'],
				),
				array(
					'name'		=> 'status',
					'content'	=> $status_ary[$user['status']],
				),
				array(
					'name'		=> 'min_limiet',
					'content'	=> $user['minlimit'],
				),
				array(
					'name'		=> 'max_limiet',
					'content'	=> $user['maxlimit'],
				),
			),
		);
	}

	$subject = '['. $systemtag .']' . $mail_subject;
	$text = str_replace(array('{{', '}}'), array('*|', '|*'), $mail_content);

	$message = array(
		'subject'		=> $subject,
		'text'			=> $text,
		'from_email'	=> $from,
		'to'			=> $to,
		'merge_vars'	=> $merge_vars,
	);

	try
	{
		$mandrill = new Mandrill();
		$mandrill->messages->send($message, true);

		$to_log = ltrim($to_log, ', ');

		log_event($s_id, 'Mail', 'Multi mail sent, subject: ' . $subject . ', from: ' . $from . ', to: ' . $to_log);

		$alert->success('Mail verzonden.');
	}
	catch (Mandrill_Error $e)
	{
		// Mandrill errors are thrown as exceptions
		log_event($s_id, 'mail', 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage());
		$alert->error('Mail fout');
	}
}

/**
 * Change password.
 */

if ($pw)
{
	$s_owner = ($pw == $s_id) ? true : false;

	if (!$s_admin && !$s_owner)
	{
		$alert->error('Je hebt onvoldoende rechten om het paswoord aan te passen voor deze gebruiker.');
		cancel($pw);
	}

	if($submit)
	{
		$password = trim($_POST['password']);

		$errors = array();

		if (empty($password) || (trim($password) == ''))
		{
			$errors[] = 'Vul paswoord in!';
		}

		if (!$s_admin && password_strength($password) < 50) // ignored readconfigfromdb('pwscore')
		{
			$errors[] = 'Te zwak paswoord.';
		}

		if (empty($errors))
		{
			$update = array(
				'password'	=> hash('sha512', $password),
				'mdate'		=> date('Y-m-d H:i:s'),
			);

			if ($db->update('users', $update, array('id' => $pw)))
			{
				$user = readuser($pw, true);
				$alert->success('Paswoord opgeslagen.');

				if (($user['status'] == 1 || $user['status'] == 2) && $_POST['notify'])
				{
					$from = readconfigfromdb('from_address');
					$to = $db->fetchColumn('select c.value
						from contact c, type_contact tc
						where tc.id = c.id_type_contact
							and tc.abbrev = \'mail\'
							and c.id_user = ?', array($pw));

					if ($to)
					{
						$url = $base_url . '/login.php?login=' . $user['letscode'];

						$subj = '[' . $systemtag;
						$subj .= '] nieuw paswoord voor je account';

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
						sendemail($from, $to, $subj, $con);
						log_event($s_id, 'Mail', 'Password change notification mail sent to ' . $to);
						$alert->success('Notificatie mail verzonden naar ' . $to);
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
			$alert->error(implode('<br>', $errors));
		}

	}

	$user = readuser($pw);

	$includejs = '<script src="' . $rootpath . 'js/generate_password.js"></script>';

	$h1 = 'Paswoord aanpassen';
	$h1 .= ($s_owner) ? '' : ' voor ' . link_user($user);
	$fa = 'key';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<button class="btn btn-default" id="generate">Genereer automatisch</button>';
	echo '<br><br>';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="password" name="password" ';
	echo 'value="' . $password . '" required>';
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

	echo aphp('users', 'id=' . $pw, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';

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

	if ($db->fetchColumn('select id from transactions where id_to = ? or id_from = ?', array($del, $del)))
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
		if ($password)
		{
			$sha512 = hash('sha512', $password);

			if ($sha512 == $db->fetchColumn('select password from users where id = ?', array($s_id)))
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
					log_event('','user','Delete user ' . $usr . ', deleted Messages ' . $msgs);

					$db->delete('messages', array('id_user' => $del));
				}

				// remove orphaned images.

				$rs = $db->prepare('SELECT mp.id, mp."PictureFile"
					FROM msgpictures mp
					LEFT JOIN messages m ON mp.msgid = m.id
					WHERE m.id IS NULL');

				$rs->execute();

				while ($row = $rs->fetch())
				{
					if ($row['PictureFile'])
					{
						$result = $s3->deleteObject(array(
							'Bucket' => $s3_img,
							'Key'    => $row['PictureFile'],
						));
					}

					$db->delete('msgpictures', array('id' => $row['id']));
				}

				// update counts for each category

				$offer_count = $want_count = array();

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

					$stats = array(
						'stat_msgs_offers'	=> ($offer_count[$cat_id]) ?: 0,
						'stat_msgs_wanted'	=> ($want_count[$cat_id]) ?: 0,
					);

					$db->update('categories', $stats, array('id' => $cat_id));
				}

				//delete contacts
				$db->delete('contact', array('id_user' => $del));

				//delete userimage from bucket;
				if ($user['PictureFile'])
				{
					$result = $s3->deleteObject(array(
						'Bucket' => $s3_img,
						'Key'    => $user['PictureFile'],
					));
				}

				//delete mongo record
				$mdb->connect();
				$mdb->users->remove(
					array('id' => (int) $del),
					array('justOne'	=> true)
				);

				//finally, the user
				$db->delete('users', array('id' => $del));
				$redis->expire($schema . '_user_' . $del, 0);

				$alert->success('De gebruiker is verwijderd.');
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
	echo '<input type="password" class="form-control" id="password" name="password" ';
	echo 'value="" required autocomplete="off">';
	echo '</div>';
	echo '</div>';

	echo aphp('users', 'id=' . $del, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';

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

	$s_owner =  ($edit && $s_id && $edit == $s_id) ? true : false;

	if ($edit && !$s_admin && !$s_owner)
	{
		$alert->error('Je hebt geen rechten om deze gebruiker aan te passen.');
		cancel($edit);
	}

	if ($s_owner && !$s_admin)
	{
		$mdb->connect();
		$cursor = $mdb->settings->findOne(array('name' => 'users_can_edit_username'));
		$username_edit = ($cursor['value']) ? true : false;
		$cursor = $mdb->settings->findOne(array('name' => 'users_can_edit_fullname'));
		$fullname_edit = ($cursor['value']) ? true : false;
	}

	if ($s_admin)
	{
		$username_edit = $fullname_edit = true;
	}

	if ($submit)
	{
		$user = array(
			'postcode'		=> $_POST['postcode'],
			'birthday'		=> $_POST['birthday'] ?: null,
			'hobbies'		=> $_POST['hobbies'],
			'comments'		=> $_POST['comments'],
			'cron_saldo'	=> $_POST['cron_saldo'] ? 1 : 0,
			'lang'			=> 'nl'
		);

		if ($s_admin)
		{
			$user += array(
				'letscode'		=> $_POST['letscode'],
				'accountrole'	=> $_POST['accountrole'],
				'status'		=> $_POST['status'],
				'admincomment'	=> $_POST['admincomment'],
				'minlimit'		=> $_POST['minlimit'],
				'maxlimit'		=> $_POST['maxlimit'],
				'presharedkey'	=> $_POST['presharedkey'],
			);

			$contact = $_POST['contact'];
			$notify = $_POST['notify'];
			$password = $_POST['password'];

			foreach ($contact as $c)
			{
				if ($c['abbrev'] == 'mail' && $c['main_mail'])
				{
					$mail = $c['value'];
					break;
				}
			}

			$mail_sql = 'select c.value
					from contact c, type_contact tc
					where c.id_type_contact = tc.id
						and tc.abbrev = \'mail\'
						and c.value = ?';
			$mail_sql_params = array($mail);

			$letscode_sql = 'select letscode
				from users
				where letscode = ?';
			$letscode_sql_params = array($user['letscode']);
		}

		if ($username_edit)
		{
			$user['login'] = $user['name'] = $_POST['name'];
		}
		if ($fullname_edit)
		{
			$user['fullname'] = $_POST['fullname'];
		}

		$fullname_access = $_POST['fullname_access'];

		$login_sql = 'select login
			from users
			where login = ?';
		$login_sql_params = array($user['login']);

		$name_sql = 'select name
			from users
			where name = ?';
		$name_sql_params = array($user['name']);

		$fullname_sql = 'select fullname
			from users
			where fullname = ?';
		$fullname_sql_params = array($user['fullname']);

		if ($edit)
		{
			$mail_sql .= ' and c.id_user <> ?';
			$mail_sql_params[] = $edit;
			$login_sql .= ' and id <> ?';
			$login_sql_params[] = $edit;
			$letscode_sql .= ' and id <> ?';
			$letscode_sql_params[] = $edit;
			$name_sql .= 'and id <> ?';
			$name_sql_params[] = $edit;
			$fullname_sql .= 'and id <> ?';
			$fullname_sql_params[] = $edit;

			$user_prefetch = readuser($edit);
		}

		$errors = array();

		if (!in_array($fullname_access, array(0, 1, 2)))
		{
			$errors[] = 'Ongeldige zichtbaarheid volledige naam.';
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
		}

		if ($fullname_edit)
		{
			if (!$user['fullname'])
			{
				$errors[] = 'Vul de volledige naam in!';
			}
			else if ($db->fetchColumn($fullname_sql, $fullname_sql_params))
			{
				$errors[] = 'De volledige naam is al in gebruik!';
			}
		}

		if (!$user['login'])
		{
			$errors[] = 'Vul een login in. (gebruikersnaam)';
		}
		else if ($db->fetchColumn($login_sql, $login_sql_params))
		{
			$errors[] = 'De login bestaat al! (gebruikersnaam)';
		}

		if ($s_admin)
		{
			if (!isset($mail))
			{
				$errors[] = 'Geen mail adres ingevuld.';
			}
			else if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
			{
				$errors[] = 'Geen geldig email adres.';
			}
			else if ($db->fetchColumn($mail_sql, $mail_sql_params))
			{
				$errors[] = 'Het mailadres is al in gebruik.';
			}

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

			if (!($user['minlimit'] == 0 || filter_var($user['minlimit'], FILTER_VALIDATE_INT)))
			{
				$errors[] = 'Geef getal op voor de minimum limiet.';
			}

			if (!($user['maxlimit'] == 0 || filter_var($user['maxlimit'], FILTER_VALIDATE_INT)))
			{
				$errors[] = 'Geef getal op voor de maximum limiet.';
			}
		}

		if (!$user_prefetch['adate'])
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

		if (!count($errors))
		{
			$contact_types = array();

			$rs = $db->prepare('SELECT abbrev, id FROM type_contact');

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$contact_types[$row['abbrev']] = $row['id'];
			}

			if ($add)
			{
				$user['creator'] = $s_id;

				$user['cdate'] = date('Y-m-d H:i:s');

				if ($user['status'] == 1)
				{
					$user['adate'] = date('Y-m-d H:i:s');
				}

				$user['password'] = hash('sha512', $password);

				if ($db->insert('users', $user))
				{
					$id = $db->lastInsertId('users_id_seq');

					$mdb->connect();
					$mdb->users->update(array(
						'id'		=> (int) $id),
						array(
							'$set' => array(
								'id'				=> (int) $id,
								'fullname_access'	=> (int) $fullname_access,
							)),
						array(
						'upsert'	=> true,
					));

					$alert->success('Gebruiker opgeslagen.');

					readuser($id, true);

					foreach ($contact as $value)
					{
						if (!$value['value'])
						{
							continue;
						}

						$insert = array(
							'value'				=> $value['value'],
							'flag_public'		=> $value['flag_public'],
							'id_type_contact'	=> $contact_types[$value['abbrev']],
							'id_user'			=> $id,
						);

						$db->insert('contact', $insert);
					}

					if ($notify && !empty($mail) && $user['status'] == 1)
					{
						$user['mail'] = $mail;
						sendactivationmail($password, $user);
						sendadminmail($user);
						$alert->success('Mail met paswoord naar de gebruiker verstuurd.');
					}
					else
					{
						$alert->warning('Geen mail met paswoord naar de gebruiker verstuurd.');
					}

					cancel($id);

					if (!readconfigfromdb('mailenabled'))
					{
						$alert->warning('Mailfuncties zijn uitgeschakeld.');
					}
				}
				else
				{
					$alert->error('Gebruiker niet opgeslagen.');
				}
			}
			else if ($edit)
			{
				$user_stored = readuser($edit);

				$user['mdate'] = date('Y-m-d H:i:s');

				if (!$user_stored['adate'] && $user['status'] == 1)
				{
					$user['adate'] = date('Y-m-d H:i:s');
				}

				if($db->update('users', $user, array('id' => $edit)))
				{
					$mdb->connect();
					$mdb->users->update(array(
							'id'	=> (int) $edit,
						),
						array(
							'$set'	=> array(
								'id'				=> (int) $edit,
								'fullname_access'	=> (int) $fullname_access,
							),
						),
						array(
							'upsert'			=> true,
					));

					readuser($edit, true);

					$alert->success('Gebruiker aangepast.');

					if ($s_admin)
					{
						$stored_contacts = array();

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
								if ($stored_contact && !$value['main_mail'])
								{
									$db->delete('contact', array('id_user' => $edit, 'id' => $value['id']));
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
								$insert = array(
									'id_type_contact'	=> $contact_types[$value['abbrev']],
									'value'				=> $value['value'],
									'flag_public'		=> $value['flag_public'],
									'id_user'			=> $edit,
								);
								$db->insert('contact', $insert);
								continue;
							}

							$contact_update = $value;

							unset($contact_update['id'], $contact_update['abbrev'],
								$contact_update['name'], $contact_update['main_mail']);

							$db->update('contact', $contact_update,
								array('id' => $value['id'], 'id_user' => $edit));
						}


						if ($user['status'] == 1 && !$user_prefetch['adate'])
						{
							if ($notify && !empty($mail))
							{
								$user['mail'] = $mail;
								sendactivationmail($password, $user);
								sendadminmail($user);
								$alert->success('Mail met paswoord naar de gebruiker verstuurd.');
							}
							else
							{
								$alert->warning('Geen mail met paswoord naar de gebruiker verstuurd.');
							}
						}
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
			$alert->error(implode('<br>', $errors));
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
			$contact = $db->fetchAll('select name, abbrev, \'\' as value, 0 as flag_public, 0 as id
				from type_contact
				where abbrev in (\'mail\', \'adr\', \'tel\', \'gsm\')');
		}

		if ($edit && $s_admin)
		{
			$contact_keys = array();

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
			$user = array(
				'minlimit'		=> readconfigfromdb('minlimit'),
				'maxlimit'		=> readconfigfromdb('maxlimit'),
				'accountrole'	=> 'user',
				'status'		=> '1',
				'cron_saldo'	=> 1,
			);

			if ($interlets)
			{
				list($schemas, $domains) = get_schemas_domains(true);

				if ($letsgroup = $db->fetchAssoc('select *
					from letsgroups
					where localletscode = ?
						and apimethod <> \'internal\'', array($interlets)))
				{
					$user['name'] = $user['fullname'] = $letsgroup['groupname'];

					if ($letsgroup['url'] && ($remote_schema = $schemas[$letsgroup['url']]))
					{
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

	array_walk($user, function(&$value, $key){ $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); });
	array_walk($contact, function(&$value, $key){ $value['value'] = htmlspecialchars($value['value'], ENT_QUOTES, 'UTF-8'); });

	$top_buttons .= aphp('users', 'status=active&view=' . $view_users, 'Lijst', 'btn btn-default', 'Lijst', 'users', true);

	$includejs = '
		<script src="' . $cdn_datepicker . '"></script>
		<script src="' . $cdn_datepicker_nl . '"></script>
		<script src="' . $rootpath . 'js/generate_password.js"></script>
		<script src="' . $rootpath . 'js/generate_password_onload.js"></script>';

	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

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
		echo 'value="' . $user['letscode'] . '" required>';
		echo '</div>';
		echo '</div>';
	}

	if ($username_edit)
	{
		echo '<div class="form-group">';
		echo '<label for="name" class="col-sm-2 control-label">Gebruikersnaam</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="name" name="name" ';
		echo 'value="' . $user['name'] . '" required>';
		echo '</div>';
		echo '</div>';
	}

	if ($fullname_edit)
	{
		echo '<div class="form-group">';
		echo '<label for="fullname" class="col-sm-2 control-label">Volledige naam (Voornaam en Achternaam)</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="fullname" name="fullname" ';
		echo 'value="' . $user['fullname'] . '" required>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="fullname_access" class="col-sm-2 control-label">Zichtbaarheid volledige naam</label>';
		echo '<div class="col-sm-10">';
		echo '<select class="form-control" id="fullname_access" name="fullname_access" required>';
		render_select_options($access_options, $user['fullname_access']);
		echo '</select>';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<label for="postcode" class="col-sm-2 control-label">Postcode</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="postcode" name="postcode" ';
	echo 'value="' . $user['postcode'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="birthday" class="col-sm-2 control-label">Geboortedatum (jjjj-mm-dd)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="birthday" name="birthday" ';
	echo 'value="' . $user['birthday'] . '" ';
	echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
	echo 'data-date-default-view="2" ';
	echo 'data-date-end-date="' . date('Y-m-d') . '" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-start-view="2" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-immediate-updates="true" ';
	echo '>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="hobbies" class="col-sm-2 control-label">Hobbies, interesses</label>';
	echo '<div class="col-sm-10">';
	echo '<textarea name="hobbies" id="hobbies" class="form-control">';
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

		echo '<div class="form-group">';
		echo '<label for="admincomment" class="col-sm-2 control-label">Commentaar van de admin</label>';
		echo '<div class="col-sm-10">';
		echo '<textarea name="admincomment" id="admincomment" class="form-control">';
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

		echo '<div class="form-group">';
		echo '<label for="presharedkey" class="col-sm-2 control-label">';
		echo 'Preshared key (enkel voor interletsaccount met eLAS-installatie)</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="presharedkey" name="presharedkey" ';
		echo 'value="' . $user['presharedkey'] . '">';
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

		$already_one_mail_input = false;

		foreach ($contact as $key => $c)
		{
			$name = 'contact[' . $key . '][value]';
			$public = 'contact[' . $key . '][flag_public]';

			echo '<div class="form-group">';
			echo '<label for="' . $name . '" class="col-sm-2 control-label">' . $c['abbrev'] . '</label>';
			echo '<div class="col-sm-10">';
			echo '<input class="form-control" id="' . $name . '" name="' . $name . '" ';
			echo 'value="' . $c['value'] . '"';
			echo ($c['abbrev'] == 'mail' && !$already_one_mail_input) ? ' required="required"' : '';
			echo ($c['abbrev'] == 'mail') ? ' type="email"' : ' type="text"';
			echo '>';
			echo '</div>';
			echo '</div>';

			echo '<div class="form-group">';
			echo '<label for="' . $public . '" class="col-sm-2 control-label">Zichtbaarheid</label>';
			echo '<div class="col-sm-10">';
			echo '<select id="' . $public . '" name="' . $public . '" class="form-control">';
			render_select_options($access_options, $c['flag_public']);
			echo '</select>';
			echo '</div>';
			echo '</div>';

			if ($c['abbrev'] == 'mail' && !$already_one_mail_input)
			{
				echo '<input type="hidden" name="contact['. $key . '][main_mail]" value="1">';
			}
			echo '<input type="hidden" name="contact['. $key . '][id]" value="' . $c['id'] . '">';
			echo '<input type="hidden" name="contact['. $key . '][name]" value="' . $c['name'] . '">';
			echo '<input type="hidden" name="contact['. $key . '][abbrev]" value="' . $c['abbrev'] . '">';

			$already_one_mail_input = ($c['abbrev'] == 'mail') ? true : $already_one_mail_input;
		}

		echo '</div>';

		if (!$user['adate'])
		{
			echo '<button class="btn btn-default" id="generate">Genereer automatisch ander paswoord</button>';
			echo '<br><br>';

			echo '<div class="form-group">';
			echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
			echo '<div class="col-sm-10">';
			echo '<input type="text" class="form-control" id="password" name="password" ';
			echo 'value="' . $password . '" required>';
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
		}
	}

	$cancel_id = ($edit) ? 'id=' . $edit : 'status=active&view=' . $view_users;
	$btn = ($edit) ? 'primary' : 'success';
	echo aphp('users', $cancel_id, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';

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
	$s_owner = ($s_id == $id) ? true : false;

	$user = readuser($id);

	if (!$s_admin && !in_array($user['status'], array(1, 2)))
	{
		$alert->error('Je hebt geen toegang tot deze gebruiker.');
		cancel();
	}

	if ($s_admin)
	{
		$count_transactions = $db->fetchColumn('select count(*)
			from transactions
			where id_from = ?
				or id_to = ?', array($id, $id));
	}

	$to = $db->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', array($user['id']));

	$and_status = ($s_admin) ? '' : ' and status in (1, 2) ';

	$next = $db->fetchColumn('select id
		from users
		where letscode > ?
		' . $and_status . '
		order by letscode asc
		limit 1', array($user['letscode']));

	$prev = $db->fetchColumn('select id
		from users
		where letscode < ?
		' . $and_status . '
		order by letscode desc
		limit 1', array($user['letscode']));

	$includejs = '<script src="' . $cdn_leaflet_js . '"></script>
		<script src="' . $rootpath . 'js/user.js"></script>
		<script src="' . $cdn_jqplot . 'jquery.jqplot.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.donutRenderer.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.cursor.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.dateAxisRenderer.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasTextRenderer.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.highlighter.min.js"></script>
		<script src="' . $rootpath . 'js/plot_user_transactions.js"></script>';

	if ($s_admin || $s_owner)
	{
		$includejs .= '<script src="' . $cdn_jquery_ui_widget . '"></script>
			<script src="' . $cdn_load_image . '"></script>
			<script src="' . $cdn_canvas_to_blob . '"></script>
			<script src="' . $cdn_jquery_iframe_transport . '"></script>
			<script src="' . $cdn_jquery_fileupload . '"></script>
			<script src="' . $cdn_jquery_fileupload_process . '"></script>
			<script src="' . $cdn_jquery_fileupload_image . '"></script>
			<script src="' . $cdn_jquery_fileupload_validate . '"></script>
			<script src="' . $rootpath . 'js/user_img.js"></script>';
	}

	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_jqplot . 'jquery.jqplot.min.css" />';
	$includecss .= '<link rel="stylesheet" type="text/css" href="' . $cdn_fileupload_css . '" />';
	$includecss .= '<link rel="stylesheet" type="text/css" href="' . $cdn_leaflet_css . '" />';

	if ($s_admin)
	{
		$top_buttons .= aphp('users', 'add=1', 'Toevoegen', 'btn btn-success', 'Gebruiker toevoegen', 'plus', true);
	}

	if ($s_admin || $s_owner)
	{
		$title = ($s_admin) ? 'Gebruiker' : 'Mijn gegevens';
		$top_buttons .= aphp('users', 'edit=' . $id, 'Aanpassen', 'btn btn-primary', $title . ' aanpassen', 'pencil', true);
		$top_buttons .= aphp('users', 'pw=' . $id, 'Paswoord aanpassen', 'btn btn-info', 'Paswoord aanpassen', 'key', true);
	}

	if ($s_admin && !$count_transactions && !$s_owner)
	{
		$top_buttons .= aphp('users', 'del=' . $id, 'Verwijderen', 'btn btn-danger', 'Gebruiker verwijderen', 'times', true);
	}

	if ($prev)
	{
		$top_buttons .= aphp('users', 'id=' . $prev, 'Vorige', 'btn btn-default', 'Vorige', 'chevron-up', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('users', 'id=' . $next, 'Volgende', 'btn btn-default', 'Volgende', 'chevron-down', true);
	}

	$top_buttons .= aphp('users', 'status=active&view=' . $view_users, 'Lijst', 'btn btn-default', 'Lijst', 'users', true);

	$status = $user['status'];
	$status = ($newusertreshold < strtotime($user['adate']) && $status == 1) ? 3 : $status;

	$status_style_ary = array(
		0	=> 'default',
		2	=> 'danger',
		3	=> 'success',
		5	=> 'warning',
		6	=> 'info',
		7	=> 'extern',
	);

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
		$attr = array('id'	=> 'btn_remove');
		if (!$user['PictureFile'])
		{
			$attr['style'] = 'display:none;';
		}

		echo '<div class="panel-footer"><span class="btn btn-success fileinput-button">';
		echo '<i class="fa fa-plus" id="img_plus"></i> Foto opladen';
		echo '<input id="fileupload" type="file" name="image" ';
		echo 'data-url="' . generate_url('users', 'img=1&id=' . $id) . '" ';
		echo 'data-data-type="json" data-auto-upload="true" ';
		echo 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
		echo 'data-max-file-size="999000" data-image-max-width="400" ';
		echo 'data-image-crop="true" ';
		echo 'data-image-max-height="400"></span>&nbsp;';

		echo aphp('users', 'img_del=1&id=' . $id, 'Foto verwijderen', 'btn btn-danger', false, 'times', false, $attr);

		echo '<p class="text-warning">Je foto moet in het jpg/jpeg formaat zijn. ';
		echo 'Je kan ook een foto hierheen verslepen.</p>';
		echo '</div>';
	}

	echo '</div></div>';

	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';
	echo '<dl>';

	$fullname_access = ($user['fullname_access']) ?: 0;

	if ($s_admin || $s_owner || $fullname_access >= $access_level)
	{
		$access = $acc_ary[$fullname_access];
		echo '<dt>';
		echo 'Volledige naam, zichtbaarheid: ';
		echo '<span class="label label-' . $access[1] . '">' . $access[0] . '</span>';
		echo '</dt>';
		echo '<dd>';
		echo htmlspecialchars($user['fullname'], ENT_QUOTES);
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
		echo '<dd>';
		dd_render($user['birthday']);
		echo '</dd>';
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
		dd_render($user['cdate']);

		echo '<dt>';
		echo 'Tijdstip activering';
		echo '</dt>';
		dd_render($user['adate']);

		echo '<dt>';
		echo 'Laatste login';
		echo '</dt>';
		dd_render($user['lastlogin']);

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

	echo '<dt>';
	echo 'Saldo, limiet min, limiet max (' . $currency . ')';
	echo '</dt>';
	echo '<dd>';
	echo '<span class="label label-info">' . $user['saldo'] . '</span>&nbsp;';
	echo '<span class="label label-danger">' . $user['minlimit'] . '</span>&nbsp;';
	echo '<span class="label label-success">' . $user['maxlimit'] . '</span>';
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

	echo '<div id="contacts" data-uid="' . $id . '" ';
	echo 'data-url="' . $rootpath . 'contacts.php?inline=1&uid=' . $id;
	echo '&' . get_session_query_param() . '"></div>';

	// response form

	if ($s_guest && !isset($s_interlets['mail']))
	{
		$placeholder = 'Als gast kan je niet het mail formulier gebruiken.';
	}
	else if ($s_owner)
	{
		$placeholder = 'Je kan geen berichten naar jezelf mailen.';
	}
	else if (!$to)
	{
		$placeholder = 'Er is geen email adres bekend van deze gebruiker.';
	}
	else
	{
		$placeholder = '';
	}

	$disabled = (empty($to) || ($s_guest && !isset($s_interlets['mail'])) || $s_owner) ? true : false;

	echo '<h3><i class="fa fa-envelop-o"></i> Stuur een bericht naar ';
	echo  link_user($id) . '</h3>';
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<textarea name="content" rows="6" placeholder="' . $placeholder . '" ';
	echo 'class="form-control" required';
	echo ($disabled) ? ' disabled' : '';
	echo '>' . $content . '</textarea>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<input type="checkbox" name="cc"';
	echo (isset($cc)) ? ' checked="checked"' : '';
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
	echo '<div id="chartdiv1" data-height="480px" data-width="960px" ';
	echo 'data-url="' . $rootpath . 'ajax/plot_user_transactions.php?id=' . $id;
	echo '&' . get_session_query_param() . '" ';
	echo 'data-users-url="' . $rootpath . 'users.php?id=" ';
	echo 'data-session-query-param="' . get_session_query_param() . '" ';
	echo 'data-user-id="' . $id . '"></div>';
	echo '</div>';
	echo '<div class="col-md-6">';
	echo '<div id="chartdiv2" data-height="480px" data-width="960px"></div>';
	echo '<h4>Interacties laatste jaar</h4>';
	echo '</div>';
	echo '</div>';

	if ($user['status'] == 1 || $user['status'] == 2)
	{
		echo '<div id="messages" data-uid="' . $id . '" ';
		echo 'data-url="' . $rootpath . 'messages.php?inline=1&uid=' . $id;
		echo '&' . get_session_query_param() . '" class="print-hide"></div>';
	}

	echo '<div id="transactions" data-uid="' . $id . '" ';
	echo 'data-url="' . $rootpath . 'transactions.php?inline=1&uid=' . $id;
	echo '&' . get_session_query_param() . '" class="print-hide"></div>';

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
$v_tiles = ($view == 'tiles') ? true : false;
$v_map = ($view == 'map') ? true : false;

$st = array(
	'active'	=> array(
		'lbl'	=> 'Actief',
		'sql'	=> 'u.status in (1, 2)',
		'st'	=> array(1, 2),
	),
	'leaving'	=> array(
		'lbl'	=> 'Uitstappers',
		'sql'	=> 'u.status = 2',
		'cl'	=> 'danger',
		'st'	=> 2,
	),
	'new'		=> array(
		'lbl'	=> 'Instappers',
		'sql'	=> 'u.status = 1 and u.adate > ?',
		'sql_bind'	=> date('Y-m-d H:i:s', $newusertreshold),
		'cl'	=> 'success',
		'st'	=> 3,
	),
);

if ($s_admin)
{
	$st = $st + array(
		'inactive'	=> array(
			'lbl'	=> 'Inactief',
			'sql'	=> 'u.status = 0',
			'cl'	=> 'inactive',
			'st'	=> 0,
		),
		'ip'		=> array(
			'lbl'	=> 'Info-pakket',
			'sql'	=> 'u.status = 5',
			'cl'	=> 'warning',
			'st'	=> 5,
		),
		'im'		=> array(
			'lbl'	=> 'Info-moment',
			'sql'	=> 'u.status = 6',
			'cl'	=> 'info',
			'st'	=> 6
		),
		'extern'	=> array(
			'lbl'	=> 'Extern',
			'sql'	=> 'u.status = 7',
			'cl'	=> 'extern',
			'st'	=> 7,
		),
		'all'		=> array(
			'lbl'	=> 'Alle',
			'sql'	=> '1 = 1',
		),
	);
}

$st_class_ary = array(
	0 => 'inactive',
	2 => 'danger',
	3 => 'success',
	5 => 'warning',
	6 => 'info',
	7 => 'extern',
);

$sql_bind = array();
$params = array();

if (!isset($st[$status]))
{
	cancel();
}

if (isset($st[$status]['sql_bind']))
{
	$sql_bind[] = $st[$status]['sql_bind'];
}

$params = array(
	'status'	=> $status,
	'view'		=> $view,
);

if ($v_list && $s_admin)
{
	if (isset($_GET['sh']))
	{
		$show_columns = $_GET['sh'];
	}
	else
	{
		$show_columns = array(
			'u'	=> array(
				'letscode'	=> 1,
				'name'		=> 1,
				'postcode'	=> 1,
				'saldo'		=> 1,
			),
		);
	}

	$adr_split = isset($_GET['adr_split']) ? $_GET['adr_split'] : '';
	$activity_days = isset($_GET['activity_days']) ? $_GET['activity_days'] : 365;
	$activity_days = ($activity_days < 1) ? 365 : $activity_days;
	$activity_filter_letscode = isset($_GET['activity_filter_letscode']) ? $_GET['activity_filter_letscode'] : '';

	$type_contact = $db->fetchAll('select id, abbrev, name from type_contact');

	$columns = array(
		'u'		=> array(
			'letscode'		=> 'Code',
			'name'			=> 'Naam',
			'fullname'		=> 'Volledige naam',
			'postcode'		=> 'Postcode',
			'accountrole'	=> 'Rol',
			'saldo'			=> 'Saldo',
			'minlimit'		=> 'Min',
			'maxlimit'		=> 'Max',
			'comments'		=> 'Commentaar',
			'admincomment'	=> 'Admin commentaar',
			'cron_saldo'	=> 'Periodieke mail',
			'cdate'			=> 'Gecreëerd',
			'mdate'			=> 'Aangepast',
			'adate'			=> 'Geactiveerd',
			'lastlogin'		=> 'Laatst ingelogd',
		),
	);

	foreach ($type_contact as $tc)
	{
		$columns['c'][$tc['abbrev']] = $tc['name'];
	}

	$columns['m'] = array(
		'demands'	=> 'Vraag',
		'offers'	=> 'Aanbod',
		'total'		=> 'Vraag en aanbod',
	);

	$columns['a'] = array(
		'trans_in'		=> 'Transacties in',
		'trans_out'		=> 'Transacties uit',
		'trans_total'	=> 'Transacties totaal',
		'amount_in'		=> $currency . ' in',
		'amount_out'	=> $currency . ' uit',
		'amount_total'	=> $currency . ' totaal',
	);

	$users = $db->fetchAll('select u.*
		from users u
		where ' . $st[$status]['sql'], $sql_bind);

	if (isset($show_columns['c']))
	{
		$c_ary = $db->fetchAll('SELECT tc.abbrev, c.id_user, c.value, c.flag_public
			FROM contact c, type_contact tc, users u
			WHERE tc.id = c.id_type_contact
				and c.id_user = u.id
				and ' . $st[$status]['sql'], $sql_bind);

		$contacts = array();

		foreach ($c_ary as $c)
		{
			$contacts[$c['id_user']][$c['abbrev']][] = array($c['value'], $c['flag_public']);
		}
	}

	if (isset($show_columns['m']))
	{
		$msgs_count = array();

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
		$activity = array();

		$ts = gmdate('Y-m-d H:i:s', time() - ($activity_days * 86400));
		$sql_bind = array($ts);
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

		$contacts = array();

		foreach ($c_ary as $c)
		{
			$contacts[$c['id_user']][$c['abbrev']][] = array($c['value'], $c['flag_public']);
		}

		if (isset($s_interlets['schema']))
		{
			$t_schema =  $s_interlets['schema'] . '.';
			$me_id = $s_interlets['id'];

			$my_adr = $db->fetchColumn('select c.value
				from ' . $t_schema . 'contact c, ' . $t_schema . 'type_contact tc, ' . $t_schema . 'users u
				where c.id_user = ?
					and c.id_type_contact = tc.id
					and tc.abbrev = \'adr\'', array($me_id));
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

if ($s_admin)
{
	if ($v_list)
	{
		$top_right .= '<a href="#" class="csv">';
		$top_right .= '<i class="fa fa-file"></i>';
		$top_right .= '&nbsp;csv</a>&nbsp;';
	}

	$top_buttons .= aphp('users', 'add=1', 'Toevoegen', 'btn btn-success', 'Gebruiker toevoegen', 'plus', true);

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
$active = ($v_tiles) ? ' active' : '';
$h1 .= aphp('users', 'status=' . $status . '&view=tiles', '', 'btn btn-default' . $active, 'tegels met foto\'s', 'th');
$active = ($v_map) ? ' active' : '';
$h1 .= aphp('users', 'status=active&view=map', '', 'btn btn-default' . $active, 'kaart', 'map-marker');
$active = ($v_list) ? ' active' : '';
$h1 .= aphp('users', 'status=' . $status . '&view=list', '', 'btn btn-default' . $active, 'lijst', 'list');
$h1 .= '</span>';

if ($s_admin && $v_list)
{
	$h1 .= '&nbsp;<button class="btn btn-info" title="Toon kolommen" ';
	$h1 .= 'data-toggle="collapse" data-target="#columns_show"';
	$h1 .= '><i class="fa fa-columns"></i></button>';
}

$h1 .= '</span>';

$top_buttons .= aphp('users', 'id=' . $s_id, 'Mijn gegevens', 'btn btn-default', 'Mijn gegevens', 'user', true);

$fa = 'users';

if ($v_list)
{
	$includejs = '<script src="' . $rootpath . 'js/calc_sum.js"></script>';
	$includejs .= '<script src="' . $rootpath . 'js/users_distance.js"></script>';

	if ($s_admin)
	{
		$includejs .= '<script src="' . $rootpath . 'js/csv.js"></script>
			<script src="' . $rootpath . 'js/table_sel.js"></script>';
	}
}
else if ($v_tiles)
{
	$includejs = '<script src="' . $cdn_isotope . '"></script>
		<script src="' . $cdn_images_loaded . '"></script>
		<script src="' . $rootpath . 'js/users_tiles.js"></script>';
}
else if ($v_map)
{
	$includejs = '<script src="' . $cdn_leaflet_js . '"></script>
		<script src="' . $cdn_leaflet_label_js . '"></script>
		<script src="' . $rootpath . 'js/users_map.js"></script>';
	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_leaflet_css . '" />
		<link rel="stylesheet" type="text/css" href="' . $cdn_leaflet_label_css . '" />';

}

include $rootpath . 'includes/inc_header.php';

if ($v_map)
{
	$data_users = array();
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
					$data_users[$user['id']] = array(
						'name'		=> $user['name'],
						'letscode'	=> $user['letscode'],
						'lat'		=> $geo['lat'],
						'lng'		=> $geo['lng'],
					);

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

if ($v_list || $v_tiles)
{
	echo '<form method="get" action="' . generate_url('users', $params) . '">';

	$params_plus = array_merge($params, get_session_query_param(true));

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
	echo '<input type="text" class="form-control" id="q" name="q" value="' . $q . '">';
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
		echo aphp('users', $nav_params, $tab['lbl'], 'bg-' . $tab['cl']) . '</li>';
	}

	echo '</ul>';
}

if ($v_list)
{
	echo '<form method="post" class="form-horizontal">';

	echo '<div class="panel panel-success printview">';
	echo '<div class="table-responsive">';

	echo '<table class="table table-bordered table-striped table-hover footable csv" ';
	echo 'data-filtering="true" data-filter-delay="0" ';
	echo 'data-filter="#q" data-filter-min="1" data-cascade="true" ';
	echo 'data-empty="Er zijn geen ' . (($s_admin) ? 'gebruikers' : 'leden') . ' volgens ';
	echo 'de selectiecriteria" data-sorting="true" data-filter-placeholder="Zoeken" ';
	echo 'data-filter-position="left"';
	if ($my_geo)
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
			$class = $st_class_ary[$row_stat];
			$class = (isset($class)) ? ' class="' . $class . '"' : '';

			$checkbox = '<input type="checkbox" name="sel[' . $id . ']" value="1"';
			$checkbox .= ($selected_users[$id]) ? ' checked="checked"' : '';
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
					echo ($key == 'letscode' || $key == 'name' || $key == 'fullname') ? link_user($u, $key) : $u[$key];
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
			$class = $st_class_ary[$row_stat];
			$class = (isset($class)) ? ' class="' . $class . '"' : '';

			$balance = $u['saldo'];
			$balance_class = ($balance < $u['minlimit'] || $balance > $u['maxlimit']) ? ' class="text-danger"' : '';

			echo '<tr' . $class . ' data-balance="' . $u['saldo'] . '"';

			echo '>';
			echo '<td>' . link_user($u, 'letscode') . '</td>';
			echo '<td>' . link_user($u, 'name') . '</td>';
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
		echo '<li class="active"><a href="#mail_tab" data-toggle="tab">Mail</a></li>';
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

		echo '<div class="form-group">';
		echo '<div class="col-sm-12">';
		echo '<input type="text" class="form-control" id="mail_subject" name="mail_subject" ';
		echo 'placeholder="Onderwerp" ';
		echo 'value="' . $mail_subject . '">';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<div class="col-sm-12">';
		echo '<textarea name="mail_content" class="form-control" id="mail_content" rows="16">';
		echo $mail_content;
		echo '</textarea>';
		echo '</div>';
		echo '</div>';

		echo sprintf($inp, 'mail_password', 'Je paswoord (extra veiligheid)', 'password', 'class="form-control"', 'mail_password');

		echo '<input type="submit" value="Zend test mail naar jezelf*" name="mail_test" class="btn btn-default">&nbsp;';
		echo '<input type="submit" value="Verzend" name="mail_submit" class="btn btn-default">';
		echo '<p>*Om een test mail te verzenden moet je je paswoord niet invullen.</p>';
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

		foreach($edit_fields_tabs as $k => $t)
		{
			echo '<div role="tabpanel" class="tab-pane" id="' . $k . '_tab">';
			echo '<h3>Veld aanpassen: ' . $t['lbl'] . '</h3>';

			if ($options = $t['options'])
			{
				echo sprintf($acc_sel, $k, $t['lbl'], render_select_options($$options, 0, false));
			}
			else if ($t['type'] == 'checkbox')
			{
				echo sprintf($inp, $k, $t['lbl'], $t['type'], 'value="1"', $k);
			}
			else
			{
				echo sprintf($inp, $k, $t['lbl'], $t['type'], 'class="form-control"', $k);
			}

			echo sprintf($inp, $k . '_password', 'Paswoord', 'password', 'class="form-control"', $k . '_password');

			echo '<input type="submit" value="Veld aanpassen" name="' . $k . '_submit" class="btn btn-primary">';

			echo '</div>';
		}

		echo '<div class="clearfix"></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</form>';
	}
}

if ($v_tiles)
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

		$url = generate_url('users', 'id=' . $u['id']);
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
	header('Location: ' . generate_url('users', (($id) ? 'id=' . $id : 'status=active&view=' . $view_users)));
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

	$from = readconfigfromdb('from_address');
	$to = readconfigfromdb('admin');

	$subject = '[';
	$subject .= $systemtag;
	$subject .= "] Account activatie";

	$content  = "*** Dit is een automatische mail van ";
	$content .= $systemtag;
	$content .= " ***\r\n\n";
	$content .= "De account " . link_user($user, null, false) ;
	$content .= " werd geactiveerd met een nieuw paswoord.\n";
	if ($user['mail'])
	{
		$content .= "Er werd een mail verstuurd naar de gebruiker op ";
		$content .= $user['mail'];
		$content .= ".\n\n";
	}
	else
	{
		$content .= "Er werd GEEN mail verstuurd omdat er geen E-mail adres bekend is voor de gebruiker.\n\n";
	}

	$content .= "OPMERKING: Vergeet niet om de gebruiker eventueel toe te voegen aan andere LETS programma's zoals mailing lists.\n\n";

	sendemail($from, $to, $subject, $content);
}

function sendactivationmail($password, $user)
{
	global $base_url, $s_id, $alert, $systemname, $systemtag;

	$from = readconfigfromdb('from_address');

	if (!empty($user["mail"]))
	{
		$to = $user["mail"];
	}
	else
	{
		$alert->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het wachtwoord op een andere manier door!');
		return 0;
	}

	$subject = '[';
	$subject .= $systemtag;
	$subject .= '] account activatie voor ' . $systemname;

	$content  = "*** Dit is een automatische mail van ";
	$content .= $systemname;
	$content .= " ***\r\n\n";
	$content .= 'Beste ';
	$content .= $user['name'];
	$content .= "\n\n";

	$content .= "Welkom bij Letsgroep $systemname";
	$content .= '. Surf naar ' . $base_url;
	$content .= " en meld je aan met onderstaande gegevens.\n";
	$content .= "\n-- Account gegevens --\n";
	$content .= "Login: ";
	$content .= $user['letscode']; 
	$content .= "\nPasswoord: ";
	$content .= $password;
	$content .= "\n-- --\n\n";

	$content .= "Je kan je gebruikersgevens, vraag&aanbod en lets-transacties";
	$content .= " zelf bijwerken op het Internet.";
	$content .= "\n\n";

	$content .= "Als je nog vragen of problemen hebt, kan je terecht bij ";
	$content .= readconfigfromdb('support');
	$content .= "\n\n";
	$content .= "Veel plezier bij het letsen! \n";

	sendemail($from,$to,$subject,$content);

	log_event($s_id, 'Mail', 'Activation mail sent to ' . $to);
}
