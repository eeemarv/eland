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

$bucket = getenv('S3_BUCKET') ?: die('No "S3_BUCKET" env config var in found!');
$bucket_url = 'https://s3.eu-central-1.amazonaws.com/' . $bucket . '/';

$role = ($edit || $pw || $img_del || $password || $submit || $img) ? 'user' : 'guest';
$role = ($add || $del) ? 'admin' : $role;
$allow_guest_post = ($role == 'guest' && $user_mail_submit) ? true : false;

if (!$inline)
{
	require_once $rootpath . 'includes/inc_passwords.php';
}

require_once $rootpath . 'includes/inc_default.php';

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;
$currency = readconfigfromdb('currency');

/**
 * mail to user
 */
if ($user_mail_submit && $id && $post)
{
	$content = $_POST['content'];
	$cc = $_POST['cc'];

	$systemtag = readconfigfromdb('systemtag');
	$systemname = readconfigfromdb('systemname');

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

	$user_me = (isset($s_interlets['schema'])) ? readconfigfromschema('systemtag', $remote_schema) . '.' : '';
	$user_me .= $me['letscode'] . ' ' . $me['name'];
	$user_me .= (isset($s_interlets['schema'])) ? ' van interlets groep ' . readconfigfromschema('systemname', $remote_schema) : '';

	$from = $db->fetchColumn('select c.value
		from ' . $t_schema . 'contact c, ' . $t_schema . 'type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', array($me_id));

	$my_contacts = $db->fetchAll('select c.value, tc.abbrev
		from ' . $t_schema . 'contact c, ' . $t_schema . 'type_contact tc
		where c.flag_public = 1
			and c.id_user = ?
			and c.id_type_contact = tc.id', array($me_id));

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
			$msg .= ($s_interlets) ? ' van letsgroep ' . readconfigfromdb('systemname') : '';
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
				'Bucket'	=> $bucket,
				'Key'		=> $user['PictureFile'],
			));
		}

		$filename = $schema . '_u_' . $id . '_' . sha1(time()) . '.jpg';

		$upload = $s3->upload($bucket, $filename, fopen($tmp_name, 'rb'), 'public-read', array(
			'params'	=> array(
				'CacheControl'	=> 'public, max-age=31536000',
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
			'Bucket'	=> $bucket,
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
	echo '<img src="' . $bucket_url . $file . '" class="img-rounded">';
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
			'tye'		=> 'text',
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
			'lbl'	=> 'Periodieke overzichtsmail (aan/uit)',
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
		$users_log .= ', ' . $row['letscode'] . ' ' . $row['name'] . ' (' . $row['id'] . ')'; 
	}
	$users_log = ltrim($users_log, ', ');

	if ($field == 'fullname_access')
	{
		$elas_mongo->connect();

		foreach ($user_ids as $user_id)
		{
			$elas_mongo->users->update(
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
	else if (array('cron_saldo' => 1, 'accountrole' => 1, 'status' => 1,
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

	$subject = '['. readconfigfromdb('systemtag') .']' . $mail_subject;
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

						$subj = '[' . readconfigfromdb('systemtag');
						$subj .= '] nieuw paswoord voor je account';

						$con = '*** Dit is een automatische mail van ';
						$con .= readconfigfromdb('systemname');
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
							'Bucket' => $bucket,
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
						'Bucket' => $bucket,
						'Key'    => $user['PictureFile'],
					));
				}

				//delete mongo record
				$elas_mongo->connect();
				$elas_mongo->users->remove(
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
	echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
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
		$elas_mongo->connect();
		$cursor = $elas_mongo->settings->findOne(array('name' => 'users_can_edit_username'));
		$username_edit = ($cursor['value']) ? true : false;
		$cursor = $elas_mongo->settings->findOne(array('name' => 'users_can_edit_fullname'));
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

		if ($add)
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

					$elas_mongo->connect();
					$elas_mongo->users->update(array(
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
					$elas_mongo->connect();
					$elas_mongo->users->update(array(
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
						$admin_mail = readconfigfromschema('admin', $remote_schema);

						foreach ($contact as $k => $c)
						{
							if ($c['abbrev'] == 'mail')
							{
								$contact[$k]['value'] = $admin_mail;
								break;
							}
						}

						// name from source is preferable
						$user['name'] = $user['fullname'] = readconfigfromschema('systemname', $remote_schema);
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
		echo '<label for="presharedkey" class="col-sm-2 control-label">Preshared key</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="presharedkey" name="presharedkey" ';
		echo 'value="' . $user['presharedkey'] . '">';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<label for="cron_saldo" class="col-sm-2 control-label">Periodieke saldo mail met recent vraag en aanbod</label>';
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

		if ($add)
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

	$includejs = '
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

	$img_src = ($user['PictureFile']) ? $bucket_url . $user['PictureFile'] : $rootpath . 'gfx/1.gif';
	echo '<img id="user_img"' . $user_img . ' class="img-rounded img-responsive center-block" ';
	echo 'src="' . $img_src . '" ';
	echo 'data-bucket-url="' . $bucket_url . '"></img>';

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
		echo 'Periodieke Saldo mail met recent vraag en aanbod';
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

	echo '<div id="messages" data-uid="' . $id . '" ';
	echo 'data-url="' . $rootpath . 'messages.php?inline=1&uid=' . $id;
	echo '&' . get_session_query_param() . '" class="print-hide"></div>';

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

$users = $db->fetchAll('select u.*
	from users u
	where ' . $st[$status]['sql'], $sql_bind);

if ($v_list)
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
}

if ($s_admin)
{
	if ($v_list)
	{
		$top_right .= '<a href="#" class="csv">';
		$top_right .= '<i class="fa fa-file"></i>';
		$top_right .= '&nbsp;csv</a>&nbsp;';
	}
/*
	$top_right .= '<a href="#" class="csv-adr">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv adres</a>';
*/
	$top_buttons .= aphp('users', 'add=1', 'Toevoegen', 'btn btn-success', 'Gebruiker toevoegen', 'plus', true);

	if ($v_list)
	{
		$top_buttons .= '<a href="#actions" class="btn btn-default"';
		$top_buttons .= ' title="Acties"><i class="fa fa-envelope-o"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Acties</span></a>';
	}

	$h1 = 'Gebruikers';
}
else
{
	$h1 = 'Leden';
}

$h1 .= '<span class="btn-group pull-right" role="group">';
$active = ($v_tiles) ? ' active' : '';
$h1 .= aphp('users', 'status=' . $status . '&view=tiles', '', 'btn btn-default' . $active, false, 'th');
$active = ($v_list) ? ' active' : '';
$h1 .= aphp('users', 'status=' . $status . '&view=list', '', 'btn btn-default' . $active, false, 'list');
$h1 .= '</span>';

$top_buttons .= aphp('users', 'id=' . $s_id, 'Mijn gegevens', 'btn btn-default', 'Mijn gegevens', 'user', true);

$fa = 'users';

if ($v_list)
{
	$includejs = '<script src="' . $rootpath . 'js/calc_sum.js"></script>
		<script src="' . $rootpath . 'js/csv.js"></script>
		<script src="' . $rootpath . 'js/table_sel.js"></script>';
}
else
{
	$includejs = '<script src="' . $cdn_isotope . '"></script>
		<script src="' . $rootpath . 'js/users_tiles.js"></script>';
}

include $rootpath . 'includes/inc_header.php';

echo '<br>';
echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';
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

echo '</form>';

echo '</div>';
echo '</div>';

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

if ($v_list)
{
	echo '<form method="post" class="form-horizontal">';

	echo '<div class="panel panel-success printview">';
	echo '<div class="table-responsive">';

	echo '<table class="table table-bordered table-striped table-hover footable csv csv-adr" ';
	echo 'data-filter="#q" data-filter-minimum="1">';
	echo '<thead>';

	echo '<tr>';
	echo '<th data-sort-initial="true">Code</th>';
	echo '<th>Naam</th>';
	//echo ($s_admin) ? '<th data-hide="phone, tablet" data-content="fullname">Volledige naam</th>' : '';
	//echo ($s_admin) ? '<th data-hide="phone, tablet">Rol</th>' : '';
	echo '<th data-hide="phone, tablet" data-sort-ignore="true">Tel</th>';
	echo '<th data-hide="phone, tablet" data-sort-ignore="true">gsm</th>';
	echo '<th data-hide="phone">Postc</th>';
	echo '<th data-hide="phone, tablet" data-sort-ignore="true">Mail</th>';
	echo '<th data-hide="phone">Saldo</th>';
	echo ($s_admin) ? '<th data-hide="phone, tablet">Rol</th>' : '';

	/*if ($s_admin)
	{
		echo '<th data-hide="phone, tablet" data-sort-ignore="true" data-content="adr">Adres</th>';
		echo '<th data-hide="all">Min</th>';
		echo '<th data-hide="all">Max</th>';
		echo '<th data-hide="all">Ingeschreven</th>';
		echo '<th data-hide="all">Geactiveerd</th>';
		echo '<th data-hide="all">Laatst aangepast</th>';
		echo '<th data-hide="all">Laatst ingelogd</th>';
		echo '<th data-hide="all">Profielfoto</th>';
		echo '<th data-hide="all">Admin commentaar</th>';
		echo '<th data-hide="all" data-sort-ignore="true">Aanpassen</th>';
	}*/

	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	foreach($users as $u)
	{
		$id = $u['id'];

		$row_stat = ($u['status'] == 1 && $newusertreshold < strtotime($u['adate'])) ? 3 : $u['status'];
		$class = $st_class_ary[$row_stat];
		$class = (isset($class)) ? ' class="' . $class . '"' : '';

		echo '<tr' . $class . ' data-balance="' . $u['saldo'] . '">';

		echo '<td>';
		if ($s_admin)
		{
			echo '<input type="checkbox" name="sel[' . $id . ']" value="1"';
			echo ($selected_users[$id]) ? ' checked="checked"' : '';
			echo '>&nbsp;';
		}
		echo link_user($u, 'letscode');
		echo '</td>';

		echo '<td>';
		echo link_user($u, 'name');
		echo '</td>';

		echo '<td>';
		echo render_contacts($contacts[$id]['tel']);
		echo '</td>';
		
		echo '<td>';
		echo render_contacts($contacts[$id]['gsm']);
		echo '</td>';
		
		echo '<td>' . $u['postcode'] . '</td>';
		
		echo '<td>';
		echo render_contacts($contacts[$id]['mail'], 'mail');
		echo '</td>';

		echo '<td>';
		$balance = $u['saldo'];
		$text_danger = ($balance < $u['minlimit'] || $balance > $u['maxlimit']) ? 'text-danger ' : '';
		echo '<span class="' . $text_danger  . '">' . $balance . '</span>';
		echo '</td>';

		if ($s_admin)
		{
			echo '<td>';
			echo $u['accountrole'];
			echo '</td>';
		}

		echo '</tr>';

	}
	echo '</tbody>';
	echo '</table>';
	echo '</div></div>';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';
	echo '<p>Totaal saldo: <span id="sum"></span> ' . $currency . '</p>';
	echo '</div></div>';

	if ($s_admin)
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
		echo '<h3>Acties met geselecteerde gebruikers</h3>';
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

		echo sprintf($inp, 'mail_password', 'Paswoord', 'password', 'class="form-control"', 'mail_password');

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
	}

	echo '</div>';

	echo '</form>';
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
			echo '<img src="' . $bucket_url . $u['PictureFile'] . '" class="img-rounded">';
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

	if (count($contacts))
	{
		end($contacts);
		$end = key($contacts);

		$f = ($abbrev == 'mail') ? '<a href="mailto:%1$s">%1$s</a>' : '%1$s';

		foreach ($contacts as $key => $contact)
		{
			if ($contact[1] >= $access_level)
			{
				echo sprintf($f, htmlspecialchars($contact[0], ENT_QUOTES));

				if ($key == $end)
				{
					break;
				}
				echo '<br>';
			}
		}
	}
	else
	{
		echo '&nbsp;';
	}
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
	$from = readconfigfromdb('from_address');
	$to = readconfigfromdb('admin');
	$systemtag = readconfigfromdb('systemtag');

	$subject = '[';
	$subject .= readconfigfromdb('systemtag');
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
