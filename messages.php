<?php

$rootpath = './';
$page_access = 'guest';
$allow_guest_post = true;
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_pagination.php';

$id = (isset($_GET['id'])) ? $_GET['id'] : false;
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$add = (isset($_GET['add'])) ? true : false;

$uid = (isset($_GET['uid'])) ? $_GET['uid'] : false;
$submit = (isset($_POST['zend'])) ? true : false;

$orderby = (isset($_GET['orderby'])) ? $_GET['orderby'] : 'm.cdate';
$asc = (isset($_GET['asc'])) ? $_GET['asc'] : 0;

$recent = (isset($_GET['recent'])) ? true : false;

$limit = (isset($_GET['limit'])) ? $_GET['limit'] : 25;
$start = (isset($_GET['start'])) ? $_GET['start'] : 0;

$q = (isset($_GET['q'])) ? $_GET['q'] : '';
$cid = (isset($_GET['cid'])) ? $_GET['cid'] : '';

$ow = (isset($_GET['ow'])) ? $_GET['ow'] : 'all';
$valid = (isset($_GET['valid'])) ? $_GET['valid'] : 'all';
$ustatus = (isset($_GET['ustatus'])) ? $_GET['ustatus'] : 'active';
$fcode = (isset($_GET['fcode'])) ? $_GET['fcode'] : '';

$img = (isset($_GET['img'])) ? true : false;
$img_del = (isset($_GET['img_del'])) ? $_GET['img_del'] : false;

$images = (isset($_FILES['images'])) ? $_FILES['images'] : null;

$mail = (isset($_POST['mail'])) ? true : false;

$selected_msgs = (isset($_POST['sel'])) ? $_POST['sel'] : [];
$extend_submit = (isset($_POST['extend_submit'])) ? true : false;
$extend = (isset($_POST['extend'])) ? $_POST['extend'] : false;
$access_submit = (isset($_POST['access_submit'])) ? true : false;

$access = $access_control->get_post_value();

if ($post && $s_guest && ($add || $edit || $del || $img || $img_del || $images
	|| $extend_submit || $access_submit || $extend || $access))
{
	$alert->error('Geen toegang als gast tot deze actie');
	cancel($id);
}

if (!$post)
{
	$extend = (isset($_GET['extend'])) ? $_GET['extend'] : false;
}

/*
 * bulk actions (set access or validity)
 */
if ($post & (($extend_submit && $extend) || ($access_submit && $access)) & ($s_admin || $s_user))
{
	if (!is_array($selected_msgs) || !count($selected_msgs))
	{
		$alert->error('Selecteer ten minste één vraag of aanbod voor deze actie.');
		cancel();
	}

	$selected_msgs = array_keys($selected_msgs);

	$validity_ary = [];

	$rows = $db->executeQuery('select id_user, id, content, validity from messages where id in (?)',
			[$selected_msgs], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($rows as $row)
	{
		if (!$s_master && $s_user && ($row['id_user'] != $s_id))
		{
			$alert->error('Je bent niet de eigenaar van vraag of aanbod ' . $row['content'] . ' ( ' . $row['id'] . ')');
			cancel();
		}

		$validity_ary[$row['id']] = $row['validity'];
	}

	if ($extend_submit)
	{
		foreach ($validity_ary as $id => $validity)
		{
			$validity = gmdate('Y-m-d H:i:s', strtotime($validity) + (86400 * $extend));

			$m = [
				'validity'		=> $validity,
				'mdate'			=> gmdate('Y-m-d H:i:s'),
				'exp_user_warn'	=> 'f',
			];

			if (!$db->update('messages', $m, ['id' => $id]))
			{
				$alert->error('Fout: ' . $row['content'] . ' is niet verlengd.');
				cancel();
			}
		}
		if (count($validity_ary) > 1)
		{
			$alert->success('De berichten zijn verlengd.');
		}
		else
		{
			$alert->success('Het bericht is verlengd.');
		}

		cancel();
	}

	if ($access_submit)
	{
		$access_error = $access_control->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}

		if (!count($errors))
		{
			$m = [
				'local' => ($access == '2') ? 'f' : 't',
				'mdate' => gmdate('Y-m-d H:i:s')
			];

			$db->beginTransaction();
			try
			{
				foreach ($validity_ary as $id => $validity)
				{
					$db->update('messages', $m, ['id' => $id]);
				}

				$db->commit();

				if (count($selected_msgs) > 1)
				{
					$alert->success('De berichten zijn aangepast.');
				}
				else
				{
					$alert->success('Het bericht is aangepast.');
				}

				cancel();
			}
			catch(Exception $e)
			{
				$db->rollback();
				throw $e;
				$alert->error('Fout bij het opslaan.');
				cancel();
			}
		}

		$alert->error($errors);
	}
}

/*
 * fetch message
 */
if ($id || $edit || $del)
{
	$id = ($id) ?: (($edit) ?: $del);

	$message = $db->fetchAssoc('SELECT m.*,
			c.id as cid,
			c.fullname as catname
		FROM messages m, categories c
		WHERE m.id = ?
			AND c.id = m.id_category', [$id]);

	if (!$message)
	{
		$alert->error('Bericht niet gevonden.');
		cancel();
	}

	$s_owner = (!$s_guest && $s_group_self && $s_id == $message['id_user'] && $message['id_user']) ? true : false;

	if ($message['local'] && $s_guest)
	{
		$alert->error('Je hebt geen toegang tot dit bericht.');
		cancel();
	}

	$ow_type = ($message['msg_type']) ? 'aanbod' : 'vraag';
	$ow_type_this = ($message['msg_type']) ? 'dit aanbod' : 'deze vraag';
	$ow_type_the = ($message['msg_type']) ? 'het aanbod' : 'de vraag';
	$ow_type_uc = ucfirst($ow_type);
	$ow_type_uc_the = ucfirst($ow_type_the);
}

/*
 * extend (link from notification mail)
 */

if ($id && $extend)
{
	if (!($s_owner || $s_admin))
	{
		$alert->error('Je hebt onvoldoende rechten om ' . $ow_type_this . ' te verlengen.');
		cancel($id);
	}

	$validity = gmdate('Y-m-d H:i:s', strtotime($message['validity']) + (86400 * $extend));

	$m = [
		'validity'		=> $validity,
		'mdate'			=> gmdate('Y-m-d H:i:s'),
		'exp_user_warn'	=> 'f',
	];

	if (!$db->update('messages', $m, ['id' => $id]))
	{
		$alert->error('Fout: ' . $ow_type_the . ' is niet verlengd.');
		cancel($id);
	}

	$alert->success($ow_type_uc_the . ' is verlengd.');
	cancel($id);
}

if ($post)
{
	$s3 = Aws\S3\S3Client::factory([
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	]);
}

/**
 * post images
 */
if ($post && $images && $id && $img
	&& ($s_admin || $s_owner))
{
	$ret_ary = [];

	$name = $images['name'];
	$size = $images['size'];

	foreach($images['tmp_name'] as $index => $tmpfile)
	{
		$name = $images['name'][$index];
		$size = $images['size'][$index];
		$type = $images['type'][$index];

		if ($type != 'image/jpeg')
		{
			$ret_ary[] = [
				'name'	=> $name,
				'size'	=> $size,
				'error' => 'ongeldig bestandstype',
			];
			continue;
		}

		if ($size > (200 * 1024))
		{
			$ret_ary[] = [
				'name'	=> $name,
				'size'	=> $size,
				'error' => 'te groot bestand',
			];
			continue;
		}

		//

		$exif = exif_read_data($tmpfile);

		$orientation = $exif['COMPUTED']['Orientation'];

		$tmpfile2 = tempnam(sys_get_temp_dir(), 'img');

		$imagine = new Imagine\Imagick\Imagine();

		$image = $imagine->open($tmpfile);

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

		$orgsize = $image->getSize();

		$width = $orgsize->getWidth();
		$height = $orgsize->getHeight();

		$newsize = ($width > $height) ? $orgsize->widen(400) : $orgsize->heighten(400);

		$image->resize($newsize);

		$image->save($tmpfile2);

		//

		try {
			$filename = $schema . '_m_' . $id . '_' . sha1(time()) . '.jpg';

			$upload = $s3->upload($s3_img, $filename, fopen($tmpfile2, 'rb'), 'public-read', [
				'params'	=> [
					'CacheControl'	=> 'public, max-age=31536000',
					'ContentType'	=> 'image/jpeg',
				],
			]);

			$db->insert('msgpictures', [
				'msgid'			=> $id,
				'"PictureFile"'	=> $filename]);

			$img_id = $db->lastInsertId('msgpictures_id_seq');

			// $size = $s3->get_object_filesize($s3_img, $filename);

			log_event('pict', 'Message-Picture ' . $filename . ' uploaded. Message: ' . $id);

			unlink($tmpfile);

			$ret_ary[] = [
				'url'			=> $s3_img_url . $filename,
				'filename'		=> $filename,
				'name'			=> $name,
				'size'			=> $size,
				'delete_url'	=> 'messages.php?id=' . $id . '&img_del=' . $img_id,
				'delete_type'	=> 'POST',
			];
		}
		catch(Exception $e)
		{
			echo $e->getMessage();
			log_event('pict', 'Upload fail : ' . $e->getMessage());
		}
	}

	header('Pragma: no-cache');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Content-Disposition: inline; filename="files.json"');
	header('X-Content-Type-Options: nosniff');
	header('Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size');

	header('Vary: Accept');

	echo json_encode($ret_ary);
	exit;
}

if ($img_del == 'all' && $id && $post)
{
	if (!($s_owner || $s_admin))
	{
		$alert->error('Je hebt onvoldoende rechten om afbeeldingen te verwijderen voor ' . $ow_type_this);
	}

	$imgs = $db->fetchAll('select * from msgpictures where msgid = ?', [$id]);

	foreach($imgs as $img)
	{
		$s3->deleteObject([
			'Bucket'	=> $s3_img,
			'Key'		=> $img['PictureFile'],
		]);
	}

	$db->delete('msgpictures', ['msgid' => $id]);

	$alert->success('De afbeeldingen voor ' . $ow_type_this . ' zijn verwijderd.');

	cancel($id);
}

/*
 * delete an image
 */
if ($img_del && $post && ctype_digit((string) $img_del))
{
	if (!($msg = $db->fetchAssoc('select m.id_user, p."PictureFile"
		from msgpictures p, messages m
		where p.msgid = m.id
			and p.id = ?', [$img_del])))
	{
		echo json_encode(['error' => 'Afbeelding niet gevonden.']);
		exit;
	}

	$s_owner = (!$s_guest && $s_group_self && $msg['id_user'] == $s_id && $msg['id_user']) ? true : false;

	if (!($s_owner || $s_admin))
	{
		echo json_encode(['error' => 'Onvoldoende rechten om deze afbeelding te verwijderen.']);
		exit;
	}

	$db->delete('msgpictures', ['id' => $img_del]);

	$s3->deleteObject([
		'Bucket'	=> $s3_img,
		'Key'		=> $msg['PictureFile'],
	]);

	echo json_encode(['success' => true]);
	exit;
}

/**
 * delete images form
 */
if ($img_del == 'all' && $id)
{
	if (!($s_admin || $s_owner))
	{
		$alert->error('Je kan geen afbeeldingen verwijderen voor ' . $ow_type_this);
		cancel($id);
	}

	$images = [];

	$st = $db->prepare('select id, "PictureFile" from msgpictures where msgid = ?');
	$st->bindValue(1, $id);
	$st->execute();

	while ($row = $st->fetch())
	{
		$images[$row['id']] = $row['PictureFile'];
	}

	if (!count($images))
	{
		$alert->error($ow_type_uc_the . ' heeft geen afbeeldingen.');
		cancel($id);
	}

	$str_this_ow = $ow_type . ' "' . aphp('messages', ['id' => $id], $message['content']) . '"';
	$h1 = 'Afbeeldingen verwijderen voor ' . $str_this_ow;
	$fa = 'newspaper-o';

	$includejs = '<script src="' . $rootpath . 'js/msg_img_del.js"></script>';

	include $rootpath . 'includes/inc_header.php';

	if ($s_admin)
	{
		echo 'Gebruiker: ' . link_user($message['id_user']);
	}

	echo '<div class="row">';

	foreach ($images as $img_id => $file)
	{
		$a_img = $s3_img_url . $file;

		echo '<div class="col-xs-6 col-md-3">';
		echo '<div class="thumbnail">';
		echo '<img src="' . $a_img . '" class="img-rounded">';

		echo '<div class="caption">';
        echo '<span class="btn btn-danger" data-img-del="' . $img_id . '" ';
        echo 'data-url="' . generate_url('messages', ['img_del' => $img_id]) . '" role="button">';
        echo '<i class="fa fa-times"></i> ';
        echo 'Verwijderen</span>';
		echo '</div>';
 		echo '</div>';
		echo '</div>';

	}

	echo '</div>';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<h3>Alle afbeeldingen verwijderen voor ' . $str_this_ow . '?</h3>';

	echo aphp('messages', ['id' => $id], 'Annuleren', 'btn btn-default'). '&nbsp;';
	echo '<input type="submit" value="Alle verwijderen" name="zend" class="btn btn-danger">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';

	exit;
}

/*
 * send email
 */
if ($mail && $post && $id)
{
	$content = $_POST['content'];
	$cc = $_POST['cc'];

	$user = readuser($message['id_user']);

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

	$subject = 'Reactie op je ' . $ow_type . ' ' . $message['content'];

	$text = 'Beste ' . $user['name'] . "\r\n\r\n";
	$text .= 'Gebruiker ' . $user_me . ' heeft een reactie op je ' . $ow_type . " verstuurd via de webtoepassing\r\n\r\n";
	$text .= '--------------------bericht--------------------' . "\r\n\r\n";
	$text .= $content . "\r\n\r\n";
	$text .= '-----------------------------------------------' . "\r\n\r\n";
	$text .= "Om te antwoorden kan je gewoon reply kiezen of de contactgegevens hieronder gebruiken\r\n\r\n";
	$text .= 'Contactgegevens van ' . $user_me . ":\r\n\r\n";

	foreach($my_contacts as $value)
	{
		$text .= '* ' . $value['abbrev'] . "\t" . $value['value'] ."\n";
	}

	if ($content)
	{
		if ($cc)
		{
			$msg = 'Dit is een kopie van het bericht dat je naar ' . $user['letscode'] . ' ';
			$msg .= $user['name'];
			$msg .= ($s_group_self) ? '' : ' van letsgroep ' . $systemname;
			$msg .= ' verzonden hebt. ';
			$msg .= "\r\n\r\n\r\n";

			mail_q(['to' => $s_schema . '.' . $s_id, 'subject' => $subject . ' (kopie)', 'text' => $msg . $text]);
		}

		$text .= "\r\n\r\nInloggen op de website: " . $base_url . "\r\n\r\n";

		mail_q(['to' => $user['id'], 'reply_to' => $s_schema . '.' . $s_id, 'subject' => $subject, 'text' => $text]);

		$alert->success('Mail verzonden.');
	}
	else
	{
		$alert->error('Fout: leeg bericht. Mail niet verzonden.');
	}
	cancel($id);
}

/*
 * delete message
 */
if ($del)
{
	if (!($s_owner || $s_admin))
	{
		$alert->error('Je hebt onvoldoende rechten om ' . $ow_type_this . ' te verwijderen.');
		cancel($del);
	}

	if($submit)
	{
		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);
		}

		$pictures = $db->fetchAll('SELECT * FROM msgpictures WHERE msgid = ?', [$del]);

		foreach($pictures as $value)
		{
			$s3->deleteObject([
				'Bucket' => $s3_img,
				'Key'    => $value['PictureFile'],
			]);
		}

		$db->delete('msgpictures', ['msgid' => $del]);

		if ($db->delete('messages', ['id' => $del]))
		{
			$column = 'stat_msgs_';
			$column .= ($message['msg_type']) ? 'offers' : 'wanted';

			$db->executeUpdate('update categories
				set ' . $column . ' = ' . $column . ' - 1
				where id = ?', [$message['id_category']]);

			$alert->success(ucfirst($ow_type_this) . ' is verwijderd.');
			cancel();
		}

		$alert->error(ucfirst($ow_type_this) . ' is niet verwijderd.');
	}

	$h1 = ucfirst($ow_type_this) . ' ';
	$h1 .= aphp('messages', ['id' => $del], $message['content']);
	$h1 .= ' verwijderen?';
	$fa = 'newspaper-o';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info printview">';

	echo '<div class="panel-heading">';

	echo '<dl>';

	echo '<dt>Wie</dt>';
	echo '<dd>';
	echo link_user($message['id_user']);
	echo '</dd>';

	echo '<dt>Categorie</dt>';
	echo '<dd>';
	echo htmlspecialchars($message['catname'], ENT_QUOTES);
	echo '</dd>';

	echo '<dt>Geldig tot</dt>';
	echo '<dd>';
	echo $message['validity'];
	echo '</dd>';

	echo '<dt>Zichtbaarheid</dt>';
	echo '<dd>';
	echo $access_control->get_label($message['local'] ? 'users' : 'interlets');
	echo '</dd>';

	echo '</dl>';

	echo '</div>';

	echo '<div class="panel-body">';
	echo htmlspecialchars($message['Description'], ENT_QUOTES);
	echo '</div>';

	echo '<div class="panel-heading">';
	echo '<h3>';
	echo '<span class="danger">';
	echo 'Ben je zeker dat ' . $ow_type_this;
	echo ' moet verwijderd worden?</span>';

	echo '</h3>';

	echo '<form method="post">';

	echo aphp('messages', ['id' => $del], 'Annuleren', 'btn btn-default'). '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	generate_form_token();
	echo '</form></p>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/*
 * edit - add
 */
if (($edit || $add))
{
	if (!($s_admin || $s_user) && $add)
	{
		$alert->error('Je hebt onvoldoende rechten om een vraag of aanbod toe te voegen.');
		cancel();
	}

	if (!($s_admin || $s_owner) && $edit)
	{
		$alert->error('Je hebt onvoldoende rechten om ' . $ow_type_this . ' aan te passen.');
		cancel($edit);
	}

	if ($submit)
	{
		$validity = $_POST['validity'];

		if (!ctype_digit((string) $validity))
		{
			$errors[] = 'De geldigheid in dagen moet een positief getal zijn.';
		}

		$vtime = time() + ((int) $validity * 86400);
		$vtime =  gmdate('Y-m-d H:i:s', $vtime);

		if ($s_admin)
		{
			list($user_letscode) = explode(' ', $_POST['user_letscode']);
			$user_letscode = trim($user_letscode);
			$user = $db->fetchAssoc('select *
				from users
				where letscode = ?
					and status in (1, 2)', [$user_letscode]);
			if (!$user)
			{
				$errors[] = 'Ongeldige letscode.' . $user_letscode;
			}
		}

		$msg = [
			'validity'		=> $_POST['validity'],
			'vtime'			=> $vtime,
			'content'		=> $_POST['content'],
			'"Description"'	=> $_POST['description'],
			'msg_type'		=> $_POST['msg_type'],
			'id_user'		=> ($s_admin) ? (int) $user['id'] : (($s_master) ? 0 : $s_id),
			'id_category'	=> $_POST['id_category'],
			'amount'		=> $_POST['amount'],
			'units'			=> $_POST['units'],
			'local'			=> ($access_control->get_post_value() == 2) ? 0 : 1,
		];

		$access_error = $access_control->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}

		if (!ctype_digit((string) $msg['amount']) && $msg['amount'] != '')
		{
			$errors[] = 'De (richt)prijs in ' . $currency . ' moet nul of een positief getal zijn.';
		}

		if (!$msg['id_category'])
		{
			$errors[] = 'Geieve een categorie te selecteren.';
		}
		else if(!$db->fetchColumn('select id from categories where id = ?', [$msg['id_category']]))
		{
			$errors[] = 'Categorie bestaat niet!';
		}

		if (!$msg['content'])
		{
			$errors[] = 'De titel ontbreekt.';
		}

		if(strlen($msg['content']) > 200)
		{
			$errors[] = 'De titel mag maximaal 200 tekens lang zijn.';
		}

		if(strlen($msg['"Description"']) > 2000)
		{
			$errors[] = 'De omschrijving mag maximaal 2000 tekens lang zijn.';
		}

		if(strlen($msg['units']) > 15)
		{
			$errors[] = '"Per (uur, stuk, ...)" mag maximaal 15 tekens lang zijn.';
		}

		if(!($db->fetchColumn('select id from users where id = ? and status <> 0', [$msg['id_user']])))
		{
			$errors[] = 'Gebruiker bestaat niet!';
		}

		if ($error_form = get_error_form_token())
		{
			$errors[] = $error_form;
		}

		if (count($errors))
		{
			$alert->error($errors);
		}
		else if ($add)
		{
			$msg['cdate'] = gmdate('Y-m-d H:i:s');
			$msg['validity'] = $msg['vtime'];

			unset($msg['vtime']);

			if (empty($msg['amount']))
			{
				unset($msg['amount']);
			}

			if ($db->insert('messages', $msg))
			{
				$id = $db->lastInsertId('messages_id_seq');

				$stat_column = 'stat_msgs_';
				$stat_column .= ($msg['msg_type']) ? 'offers' : 'wanted';

				$db->executeUpdate('update categories set ' . $stat_column . ' = ' . $stat_column . ' + 1 where id = ?', [$msg['id_category']]);

				$alert->success('Nieuw vraag of aanbod toegevoegd.');
				cancel($id);
			}
			else
			{
				$alert->error('Fout bij het opslaan van vraag of aanbod.');
			}
		}
		else if ($edit)
		{
			if(empty($msg['validity']))
			{
				unset($msg['validity']);
			}
			else
			{
				$msg['validity'] = $msg['vtime'];
			}
			$msg['mdate'] = gmdate('Y-m-d H:i:s');
/*
			$description = $msg['description'];
*/
			unset($msg['vtime']);

			if (empty($msg['amount']))
			{
				unset($msg['amount']);
			}

			$db->beginTransaction();

			try
			{
				$db->update('messages', $msg, ['id' => $edit]);

	//			$db->update('messages', ['"Description"' => $description, ['id' => $id]]);

				if ($msg['msg_type'] != $msg['msg_type'] || $msg['id_category'] != $msg['id_category'])
				{
					$column = 'stat_msgs_';
					$column .= ($msg['msg_type']) ? 'offers' : 'wanted';

					$db->executeUpdate('update categories
						set ' . $column . ' = ' . $column . ' - 1
						where id = ?', [$msg['id_category']]);

					$column = 'stat_msgs_';
					$column .= ($msg['msg_type']) ? 'offers' : 'wanted';

					$db->executeUpdate('update categories
						set ' . $column . ' = ' . $column . ' + 1
						where id = ?', [$msg['id_category']]);
				}
				$db->commit();
				$alert->success('Vraag/aanbod aangepast');
				cancel($edit);
			}
			catch(Exception $e)
			{
				$db->rollback();
				throw $e;
				exit;
			}
		}
		else
		{
			$alert->error('Fout: onbepaalde actie.');
			cancel();
		}

		$msg['description'] = $msg['"Description"'];
	}
	else if ($edit)
	{
		$msg =  $db->fetchAssoc('select m.*,
			m."Description" as description
			from messages m
			where m.id = ?', [$edit]);
		$msg['description'] = $msg['Description'];
		unset($msg['Description']);

		$rev = round((strtotime($msg['validity']) - time()) / (86400));
		$msg['validity'] = ($rev < 1) ? 0 : $rev;

		$user = readuser($msg['id_user']);

		$user_letscode = $user['letscode'] . ' ' . $user['name'];
	}
	else if ($add)
	{
		$msg = [
			'validity'		=> readconfigfromdb('msgs_days_default'),
			'content'		=> '',
			'description'	=> '',
			'msg_type'		=> '1',
			'id_user'		=> ($s_master) ? 0 : $s_id,
			'id_category'	=> '',
			'amount'		=> '',
			'units'			=> '',
			'local'			=> 0,
		];

		$uid = (isset($_GET['uid']) && $s_admin) ? $_GET['uid'] : (($s_master) ? 0 : $s_id);

		if ($s_master)
		{
			$user_letscode = '';
		}
		else
		{
			$user = readuser($uid);

			$user_letscode = $user['letscode'] . ' ' . $user['name'];
		}
	}

	$cat_list = ['' => ''];

	$rs = $db->prepare('SELECT id, fullname  FROM categories WHERE leafnote=1 order by fullname');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$cat_list[$row['id']] = $row['fullname'];
	}

	array_walk($msg, function(&$value, $key){ $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); });

	$top_buttons .= aphp('messages', ['view' => $view_messages], 'Lijst', 'btn btn-default', 'Alle vraag en aanbod', 'newspaper-o', true);

	if (!$s_master)
	{
		$top_buttons .= aphp('messages', ['uid' => $s_id, 'view' => $view_messages], 'Mijn vraag en aanbod', 'btn btn-default', 'Mijn vraag en aanbod', 'user', true);
	}

	if ($s_admin)
	{
		$includejs = '
			<script src="' . $cdn_typeahead . '"></script>
			<script src="' . $rootpath . 'js/typeahead.js"></script>
			<script src="' . $rootpath . 'js/access_input_cache.js"></script>';
	}

	$h1 = ($add) ? 'Nieuw Vraag of Aanbod toevoegen' : 'Vraag of Aanbod aanpassen';
	$fa = 'newspaper-o';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	if($s_admin)
	{
		echo '<div class="form-group">';
		echo '<label for="user_letscode" class="col-sm-2 control-label">';
		echo '<span class="label label-info">Admin</span> Gebruiker</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="user_letscode" name="user_letscode" ';
		echo 'data-typeahead="' . get_typeahead('users_active') . '" ';
		echo 'value="' . $user_letscode . '" required>';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<label for="msg_type" class="col-sm-2 control-label">Vraag/Aanbod</label>';
	echo '<div class="col-sm-10">';
	echo '<select name="msg_type" id="msg_type" class="form-control" required>';
	render_select_options(['1' => 'Aanbod', '0' => 'Vraag'], $msg['msg_type']);
	echo "</select>";
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="content" class="col-sm-2 control-label">Titel</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="content" name="content" ';
	echo 'value="' . $msg['content'] . '" maxlength="200" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
	echo '<div class="col-sm-10">';
	echo '<textarea name="description" class="form-control" id="description" rows="4" maxlength="2000">';
	echo $msg['description'];
	echo '</textarea>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="id_category" class="col-sm-2 control-label">Categorie</label>';
	echo '<div class="col-sm-10">';
	echo '<select name="id_category" id="id_category" class="form-control" required>';
	render_select_options($cat_list, $msg['id_category']);
	echo "</select>";
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="validity" class="col-sm-2 control-label">Geldigheid in dagen</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="number" class="form-control" id="validity" name="validity" min="1" ';
	echo 'value="' . $msg['validity'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="amount" class="col-sm-2 control-label">Aantal ' . $currency . '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="number" class="form-control" id="amount" name="amount" min="0" ';
	echo 'value="' . $msg['amount'] . '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="units" class="col-sm-2 control-label">Per (uur, stuk, ...)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="units" name="units" ';
	echo 'value="' . $msg['units'] . '">';
	echo '</div>';
	echo '</div>';

	$access_value = $edit ? ($msg['local'] ? 'users' : 'interlets') : false;

	echo $access_control->get_radio_buttons('messages', $access_value, 'admin');

	$btn = ($edit) ? 'primary' : 'success';

	echo aphp('messages', ['id' => $id], 'Annuleren', 'btn btn-default'). '&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-' . $btn . '">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * show a message
 */
if ($id)
{
	$cc = ($post) ? $cc : 1;

	$user = readuser($message['id_user']);

	$to = $db->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', [$user['id']]);

	$mail_to = getmailadr($user['id']);
	$mail_from = ($s_schema && !$s_master) ? getmailadr($s_schema . '.' . $s_id) : [];

	$balance = $user['saldo'];

	$images = [];

	$st = $db->prepare('select id, "PictureFile" from msgpictures where msgid = ?');
	$st->bindValue(1, $id);
	$st->execute();

	while ($row = $st->fetch())
	{
		$images[$row['id']] = $row['PictureFile'];
	}

	$and_local = ($s_guest) ? ' and local = \'f\' ' : '';

	$prev = $db->fetchColumn('select id
		from messages
		where id > ?
		' . $and_local . '
		order by id asc
		limit 1', [$id]);

	$next = $db->fetchColumn('select id
		from messages
		where id < ?
		' . $and_local . '
		order by id desc
		limit 1', [$id]);

	$title = $message['content'];

	$contacts = $db->fetchAll('select c.*, tc.abbrev
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and c.flag_public = 1', [$user['id']]);

	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_fileupload_css . '" />';
	$includecss .= '<link rel="stylesheet" type="text/css" href="' . $cdn_leaflet_css . '" />';

	$includejs = '<script src="' . $cdn_leaflet_js . '"></script>
		<script src="' . $cdn_jssor_slider_mini_js . '"></script>
		<script src="' . $rootpath . 'js/msg.js"></script>';

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
			<script src="' . $rootpath . 'js/msg_img.js"></script>';
	}

	if ($s_user || $s_admin)
	{
		$top_buttons .= aphp('messages', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Vraag of aanbod toevoegen', 'plus', true);

		if ($s_admin || $s_owner)
		{
			$top_buttons .= aphp('messages', ['edit' => $id], 'Aanpassen', 'btn btn-primary', $ow_type_uc . ' aanpassen', 'pencil', true);
			$top_buttons .= aphp('messages', ['del' => $id], 'Verwijderen', 'btn btn-danger', $ow_type_uc . ' verwijderen', 'times', true);
		}
	}

	if ($message['msg_type'] == 1
		&& ($s_admin || (!$s_owner
		&& $user['status'] != 7
		&& !($s_guest && $s_group_self))))
	{
			$tus = ['add' => 1, 'mid' => $id];

			if (!$s_group_self)
			{
				$tus['tus'] = $schema;
			}

			$top_buttons .= aphp('transactions', $tus, 'Transactie',
				'btn btn-warning', 'Transactie voor dit aanbod',
				'exchange', true, false, $s_schema);
	}

	if ($prev)
	{
		$top_buttons .= aphp('messages', ['id' => $prev], 'Vorige', 'btn btn-default', 'Vorige', 'chevron-up', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('messages', ['id' => $next], 'Volgende', 'btn btn-default', 'Volgende', 'chevron-down', true);
	}

	$top_buttons .= aphp('messages', ['view' => $view_messages], 'Lijst', 'btn btn-default', 'Alle vraag en aanbod', 'newspaper-o', true);

	if (($s_user || $s_admin) && !$s_master)
	{
		$top_buttons .= aphp('messages', ['uid' => $s_id, 'view' => $view_messages], 'Mijn vraag en aanbod', 'btn btn-default', 'Mijn vraag en aanbod', 'user', true);
	}

	$h1 = $ow_type_uc;
	$h1 .= ': ' . htmlspecialchars($message['content'], ENT_QUOTES);
	$h1 .= (strtotime($message['validity']) < time()) ? ' <small><span class="text-danger">Vervallen</span></small>' : '';
	$fa = 'newspaper-o';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="row">';

	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-body">';

	echo '<div id="no_images" class="text-center center-body" style="display: none;">';
	echo '<i class="fa fa-image fa-5x"></i> ';
	echo '<p>Er zijn geen afbeeldingen voor ' . $ow_type_this . '</p>';
	echo '</div>';

	echo '<div id="images_con" ';
	echo 'data-bucket-url="' . $s3_img_url . '" ';
	echo 'data-images="' . implode(',', $images) . '">';
	echo '</div>';

	echo '</div>'; // panel-body

	if ($s_admin || $s_owner)
	{
		echo '<div class="panel-footer"><span class="btn btn-success fileinput-button">';
		echo '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
		echo '<input id="fileupload" type="file" name="images[]" ';
		echo 'data-url="' . generate_url('messages', ['img' => 1, 'id' => $id]) . '" ';
		echo 'data-data-type="json" data-auto-upload="true" ';
		echo 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
		echo 'data-max-file-size="999000" ';
		echo 'multiple></span>&nbsp;';

		echo aphp('messages', ['img_del' => 'all', 'id' => $id], 'Afbeeldingen verwijderen', 'btn btn-danger', false, 'times', false,
			['id' => 'btn_remove', 'style' => 'display:none;']);

		echo '<p class="text-warning">Afbeeldingen moeten in het jpg/jpeg formaat zijn. ';
		echo 'Je kan ook afbeeldingen hierheen verslepen.</p>';
		echo '</div>';
	}

	echo '</div>';
	echo '</div>';

	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<p><b>Omschrijving</b></p>';
	echo '</div>';
	echo '<div class="panel-body">';
	echo '<p>';
	if ($message['Description'])
	{
		echo htmlspecialchars($message['Description'],ENT_QUOTES);
	}
	else
	{
		echo '<i>Er werd geen omschrijving ingegeven.</i>';
	}
	echo '</p>';
	echo '</div></div>';

	echo '<div class="panel panel-default printview">';
	echo '<div class="panel-heading">';

	echo '<dl>';
	echo '<dt>';
	echo '(Richt)prijs';
	echo '</dt>';
	echo '<dd>';
	$units = ($message['units']) ? ' per ' . $message['units'] : '';
	echo (empty($message['amount'])) ? 'niet opgegeven.' : $message['amount'] . ' ' . $currency . $units;
	echo '</dd>';

	echo '<dt>Van gebruiker: ';
	echo '</dt>';
	echo '<dd>';
	echo link_user($user);
	echo ' (saldo: <span class="label label-info">' . $balance . '</span> ' .$currency . ')';
	echo '</dd>';

	echo '<dt>Plaats</dt>';
	echo '<dd>' . $user['postcode'] . '</dd>';

	echo '<dt>Aangemaakt op</dt>';
	echo '<dd>' . $date_format->get($message['cdate'], 'day') . '</dd>';

	echo '<dt>Geldig tot</dt>';
	echo '<dd>' . $date_format->get($message['validity'], 'day') . '</dd>';

	if ($s_admin || $s_owner)
	{
		echo '<dt>Verlengen</dt>';
		echo '<dd>' . aphp('messages', ['id' => $id, 'extend' => 30], '1 maand', 'btn btn-default btn-xs') . '&nbsp;';
		echo aphp('messages', ['id' => $id, 'extend' => 180], '6 maanden', 'btn btn-default btn-xs') . '&nbsp;';
		echo aphp('messages', ['id' => $id, 'extend' => 365], '1 jaar', 'btn btn-default btn-xs') . '</dd>';
	}

	echo '<dt>Zichtbaarheid</dt>';
	echo '<dd>' . $access_control->get_label($message['local'] ? 'users' : 'interlets') . '</dd>';

	echo '</dl>';

	echo '</div>';
	echo '</div>'; // panel

	echo '</div>'; //col-md-6
	echo '</div>'; //row

	echo '<div id="contacts" '; // data-uid="' . $message['id_user'] . '" ';
	echo 'data-url="' . $rootpath . 'contacts.php?inline=1&uid=' . $message['id_user'];
	echo '&' . http_build_query(get_session_query_param()) . '"></div>';

// response form

	if ($s_elas_guest)
	{
		$placeholder = 'Als eLAS gast kan je niet het mail formulier gebruiken.';
	}
	else if ($s_owner)
	{
		$placeholder = 'Je kan geen reacties op je eigen berichten sturen.';
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

	echo '<h3><i class="fa fa-envelop-o"></i> Stuur een reactie naar ';
	echo  link_user($message['id_user']) . '</h3>';
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<textarea name="content" rows="6" placeholder="' . $placeholder . '" ';
	echo 'class="form-control" required';
	echo ($disabled) ? ' disabled' : '';
	echo '>';
	echo (isset($content)) ? $content : '';
	echo '</textarea>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<input type="checkbox" name="cc"';
	echo ($cc) ? ' checked="checked"' : '';
	echo ' value="1" >Stuur een kopie naar mijzelf';
	echo '</div>';
	echo '</div>';

	echo '<input type="submit" name="mail" value="Versturen" class="btn btn-default"';
	echo ($disabled) ? ' disabled' : '';
	echo '>';
	echo '</form>';

	echo '</div>';
	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/*
 * list messages
 */

if (!($view || $inline))
{
	cancel();
}

$s_owner = (!$s_guest && $s_group_self && $s_id == $uid && $s_id && $uid) ? true : false;

$v_list = (($view == 'list' || $inline) && !$recent) ? true : false;
$v_extended = (($view == 'extended' && !$inline) || $recent) ? true : false;
$v_map = ($view == 'map' && !($inline || $recent)) ? true : false;

$params = [
	'view'		=> $view,
	'orderby'	=> $orderby,
	'asc'		=> $asc,
	'limit'		=> $limit,
	'start'		=> $start,
];

$params_sql = $where_sql = [];

if ($uid)
{
	$user = readuser($uid);

	$where_sql[] = 'u.id = ?';
	$params_sql[] = $uid;
	$params['uid'] = $uid;

	$fcode = link_user($user, false, false);
}

if (!$uid)
{
	if ($fcode)
	{
		list($fcode) = explode(' ', trim($fcode));

		$fuid = $db->fetchColumn('select id from users where letscode = \'' . $fcode . '\'');

		if ($fuid)
		{
			$where_sql[] = 'u.id = ?';
			$params_sql[] = $fuid;

			$fcode = link_user($fuid, false, false);
		}
		else
		{
			$where_sql[] = '1 = 2';
		}

		$params['fcode'] = $fcode;
	}
}

if ($q)
{
	$where_sql[] = 'm.content ilike ?';
	$params_sql[] = '%' . $q . '%';
	$params['q'] = $q;
}

if ($cid)
{
	$cat_ary = [];

	$st = $db->prepare('select id from categories where id_parent = ?');
	$st->bindValue(1, $cid);
	$st->execute();

	while ($row = $st->fetch())
	{
		$cat_ary[] = $row['id'];
	}

	if (count($cat_ary))
	{
		$where_sql[] = 'm.id_category in (' . implode(',', $cat_ary) . ')';
	}
	else
	{
		$where_sql[] = 'm.id_category = ?';
		$params_sql[] = $cid;
	}

	$params['cid'] = $cid;
}

switch ($valid)
{
	case 'n':
		$where_sql[] = 'm.validity < now()';
		$params['valid'] = '0';
		break;
	case 'y':
		$where_sql[] = 'm.validity >= now()';
		$params['valid'] = '1';
		break;
	default:
		$params['valid'] = 'all';
		break;
}

switch ($ow)
{
	case 'o':
		$where_sql[] = 'm.msg_type = 1';
		$params['ow'] = 'o';
		break;
	case 'w':
		$where_sql[] = 'm.msg_type = 0';
		$params['ow'] = 'w';
		break;
	default:
		$params['ow'] = 'all';
		break;
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
	default:
		$where_sql[] = 'u.status in (1, 2)';
		$params['ustatus'] = 'active';
		break;
}

if ($s_guest)
{
	$where_sql[] = 'm.local = \'f\'';
}

if (count($where_sql))
{
	$where_sql = ' and ' . implode(' and ', $where_sql) . ' ';
}
else
{
	$where_sql = '';
}

$query = 'select m.*, u.postcode
	from messages m, users u
		where m.id_user = u.id' . $where_sql . '
	order by ' . $orderby . ' ';

$row_count = $db->fetchColumn('select count(m.*)
	from messages m, users u
	where m.id_user = u.id' . $where_sql, $params_sql);

$query .= ($asc) ? 'asc ' : 'desc ';
$query .= ' limit ' . $limit . ' offset ' . $start;

$messages = $db->fetchAll($query, $params_sql);

if ($v_extended)
{
	$ids = $imgs = [];

	foreach ($messages as $msg)
	{
		$ids[] = $msg['id'];
	}

	$_imgs = $db->executeQuery('select mp.msgid, mp."PictureFile"
		from msgpictures mp
		where msgid in (?)',
		[$ids],
		[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($_imgs as $_img)
	{
		if (isset($imgs[$_img['msgid']]))
		{
			continue;
		}

		$imgs[$_img['msgid']] = $_img['PictureFile'];
	}
}

$pagination = new pagination('messages', $row_count, $params, $inline);

$asc_preset_ary = [
	'asc'	=> 0,
	'indicator' => '',
];

$tableheader_ary = [
	'm.msg_type' => array_merge($asc_preset_ary, [
		'lbl' => 'V/A']),
	'm.content' => array_merge($asc_preset_ary, [
		'lbl' => 'Wat']),
];

if (!$uid)
{
	$tableheader_ary += [
		'u.name'	=> array_merge($asc_preset_ary, [
			'lbl' 		=> 'Wie',
			'data_hide' => 'phone,tablet',
		]),
		'u.postcode'	=> array_merge($asc_preset_ary, [
			'lbl' 		=> 'Postcode',
			'data_hide'	=> 'phone,tablet',
		]),
	];
}

if (!$cid)
{
	$tableheader_ary += [
		'm.id_category' => array_merge($asc_preset_ary, [
			'lbl' 		=> 'Categorie',
			'data_hide'	=> 'phone, tablet',
		]),
	];
}

$tableheader_ary += [
	'm.validity' => array_merge($asc_preset_ary, [
		'lbl' 	=> 'Geldig tot',
		'data_hide'	=> 'phone, tablet',
	]),
];

if (!$s_guest)
{
	$tableheader_ary += [
		'm.local' => array_merge($asc_preset_ary, [
			'lbl' 	=> 'Zichtbaarheid',
			'data_hide'	=> 'phone, tablet',
		]),
	];
}

$tableheader_ary[$orderby]['asc'] = ($asc) ? 0 : 1;
$tableheader_ary[$orderby]['indicator'] = ($asc) ? '-asc' : '-desc';

unset($tableheader_ary['m.cdate']);

$cats = ['' => '-- alle categorieën --'];

$categories = $cat_params  = [];

if ($uid)
{
	$st = $db->executeQuery('select c.*
		from categories c, messages m
		where m.id_category = c.id
			and m.id_user = ?
		order by c.fullname', [$uid]);
}
else
{
	$st = $db->executeQuery('select * from categories order by fullname');
}

while ($row = $st->fetch())
{
	$cats[$row['id']] = ($row['id_parent']) ? ' . . . . . ' : '';
	$cats[$row['id']] .= $row['name'];
	$count_msgs = $row['stat_msgs_offers'] + $row['stat_msgs_wanted'];
	if ($row['id_parent'] && $count_msgs)
	{
		$cats[$row['id']] .= ' (' . $count_msgs . ')';
	}

	$categories[$row['id']] = $row['fullname'];

	$cat_params[$row['id']] = $params;
	$cat_params[$row['id']]['cid'] = $row['id'];
	$cat_params[$row['id']]['view'] = $view_messages;
}

if ($s_admin || $s_user)
{
	if (!$inline)
	{
		$top_buttons .= aphp('messages', ['add' => 1], 'Toevoegen', 'btn btn-success', 'Vraag of aanbod toevoegen', 'plus', true);
	}

	if ($uid)
	{
		if ($s_admin && !$s_owner)
		{
			$str = 'Vraag of aanbod voor ' . link_user($uid, false, false);
			$top_buttons .= aphp('messages', ['add' => 1, 'uid' => $uid], $str, 'btn btn-success', $str, 'plus', true);
		}

		if (!$inline)
		{
			$top_buttons .= aphp('messages', ['view' => $view_messages], 'Lijst', 'btn btn-default', 'Lijst alle vraag en aanbod', 'newspaper-o', true);
		}
	}
	else if (!$s_master)
	{
		$top_buttons .= aphp('messages', ['uid' => $s_id], 'Mijn vraag en aanbod', 'btn btn-default', 'Mijn vraag en aanbod', 'user', true);
	}
}

if ($s_admin && $v_list)
{
	$top_right .= '<a href="#" class="csv">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv</a>';
}

$panel_collapse = (($fcode && !$uid) || $ow != 'all' || $valid != 'all' || $ustatus != 'active') ? false : true;

$filtered = ($q || !$panel_collapse) ? true : false;

if ($uid)
{
	if ($s_owner && !$inline)
	{
		$h1 = 'Mijn vraag en aanbod';
	}
	else
	{
		$h1 = aphp('messages', ['uid' => $uid, 'view' => $view_messages], 'Vraag en aanbod');
		$h1 .= ' van ' . link_user($uid);
	}
}
else if ($recent)
{
	$h1 = aphp('messages', ['view' => $view_messages], 'Recent Vraag en aanbod');
}
else
{
	$h1 = 'Vraag en aanbod';
}

$h1 .= ($cid) ? ', categorie "' . $categories[$cid] . '"' : '';
$h1 .= ($filtered) ? ' <small>gefilterd</small>' : '';

$fa = 'newspaper-o';

if (!$inline)
{
	$v_params = $params;
	$h1 .= '<span class="pull-right hidden-xs">';
	$h1 .= '<span class="btn-group" role="group">';

	$active = ($v_list) ? ' active' : '';
	$v_params['view'] = 'list';
	$h1 .= aphp('messages', $v_params, '', 'btn btn-default' . $active, 'lijst', 'align-justify');

	$active = ($v_extended) ? ' active' : '';
	$v_params['view'] = 'extended';
	$h1 .= aphp('messages', $v_params, '', 'btn btn-default' . $active, 'Lijst met omschrijvingen', 'th-list');

	$h1 .= '</span></span>';

	$includejs = '<script src="' . $rootpath . 'js/csv.js"></script>
		<script src="' . $rootpath . 'js/msgs.js"></script>
		<script src="' . $rootpath . 'js/table_sel.js"></script>
		<script src="' . $cdn_typeahead . '"></script>
		<script src="' . $rootpath . 'js/typeahead.js"></script>';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="get" class="form-horizontal">';

	echo '<div class="row">';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-search"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="q" value="' . $q . '" name="q" placeholder="Zoeken">';
	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-5 col-xs-10">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-clone"></i>';
	echo '</span>';
	echo '<select class="form-control" id="cid" name="cid">';
	render_select_options($cats, $cid);
	echo '</select>';
	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-2 col-xs-2">';
	echo '<button class="btn btn-default btn-block" title="Meer filters" ';
	echo 'type="button" ';
	echo 'data-toggle="collapse" data-target="#filters">';
	echo '<i class="fa fa-caret-down"></i><span class="hidden-xs hidden-sm"> ';
	echo 'Meer</span></button>';
	echo '</div>';

	echo '</div>';

	echo '<div id="filters"';
	echo ($panel_collapse) ? ' class="collapse"' : '';
	echo '>';

	echo '<div class="row">';

	$offerwant_options = [
		'all'	=> 'Vraag en aanbod',
		'o'		=> 'Enkel aanbod',
		'w'		=> 'Enkel vraag',
	];

	echo '<div class="col-sm-6">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo 'V/A';
	echo '</span>';
	echo '<select class="form-control" id="ow" name="ow">';
	render_select_options($offerwant_options, $ow);
	echo '</select>';
	echo '</div>';
	echo '</div>';

	$valid_options = [
		'all'	=> 'Geldig en vervallen',
		'y'		=> 'Enkel geldig',
		'n'		=> 'Enkel vervallen',
	];

	echo '<div class="col-sm-6">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-check-square-o"></i>';
	echo '</span>';
	echo '<select class="form-control" id="valid" name="valid">';
	render_select_options($valid_options, $valid);
	echo '</select>';
	echo '</div>';
	echo '</div>';

	echo '</div>';

	echo '<div class="row">';

	$user_status_options = [
		'active'	=> 'Alle leden',
		'new'		=> 'Enkel instappers',
		'leaving'	=> 'Enkel uitstappers',
	];

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo 'Van ';
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

	echo '<input type="text" class="form-control" ';
	echo 'aria-describedby="fcode_addon" ';
	echo 'data-typeahead="' . get_typeahead('users_active') . '" '; 
	echo 'name="fcode" id="fcode" placeholder="letscode" ';
	echo 'value="' . $fcode . '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-2">';
	echo '<input type="submit" value="Toon" class="btn btn-default btn-block">';
	echo '</div>';

	echo '</div>';
	echo '</div>';

	$params_form = $params;
	unset($params_form['cid'], $params_form['q'], $params_form['valid'], $params_form['ow']);
	unset($params_form['fcode'], $params_form['ustatus'], $params_form['uid']);
	unset($params_form['start']);

	$params_form['r'] = $s_accountrole;
	$params_form['u'] = $s_id;

	if (!$s_group_self)
	{
		$params_form['s'] = $s_schema;
	}

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
}

if ($inline)
{
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	echo '<h3><i class="fa fa-newspaper-o"></i> ' . $h1;
	echo ($recent) ? '' : '<span class="inline-buttons">' . $top_buttons . '</span>';
	echo '</h3>';
}

if (!$recent)
{
	$pagination->render();
}

if (!count($messages))
{
	echo '<br>';
	echo '<div class="panel panel-info">';
	echo '<div class="panel-body">';
	echo '<p>Er zijn geen resultaten.</p>';
	echo '</div></div>';

	if (!$recent)
	{
		$pagination->render();
	}

	if (!$inline)
	{
		include $rootpath . 'includes/inc_footer.php';
	}
	exit;
}

if ($v_list)
{
	echo '<div class="panel panel-info printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-striped table-bordered table-hover footable csv" ';
	echo 'id="msgs" data-sort="false">';

	echo '<thead>';
	echo '<tr>';

	$th_params = $params;

	$th_params['start'] = 0;

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

			echo '<a href="' . generate_url('messages', $th_params) . '">';
			echo $data['lbl'] . '&nbsp;<i class="fa fa-sort' . $data['indicator'] . '"></i>';
			echo '</a>';
		}
		echo '</th>';
	}

	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($messages as $msg)
	{
		echo '<tr';
		echo (strtotime($msg['validity']) < time()) ? ' class="danger"' : '';
		echo '>';

		echo '<td>';

		if (!$inline && ($s_admin || $s_owner))
		{
			echo '<input type="checkbox" name="sel[' . $msg['id'] . ']" value="1"';
			echo (isset($selected_msgs[$id])) ? ' checked="checked"' : '';
			echo '>&nbsp;';
		}

		echo ($msg['msg_type']) ? 'Aanbod' : 'Vraag';
		echo '</td>';

		echo '<td>';
		echo aphp('messages', ['id' => $msg['id']], $msg['content']);
		echo '</td>';

		if (!$uid)
		{
			echo '<td>';
			echo link_user($msg['id_user']);
			echo '</td>';

			echo '<td>';
			echo $msg['postcode'];
			echo '</td>';
		}

		if (!$cid)
		{
			echo '<td>';
			echo aphp('messages', $cat_params[$msg['id_category']], $categories[$msg['id_category']]);
			echo '</td>';
		}

		echo '<td>';
		echo $date_format->get($msg['validity'], 'day');
		echo '</td>';

		if (!$s_guest)
		{
			echo '<td>' . $access_control->get_label($msg['local'] ? 'users' : 'interlets') . '</td>';
		}

		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';

	echo '</div>';
	echo '</div>';
}
else if ($v_extended)
{
	$time = time();

	foreach ($messages as $msg)
	{
		$type_str = ($msg['msg_type']) ? 'Aanbod' : 'Vraag';

		$sf_owner = ($s_group_self && $msg['id_user'] == $s_id) ? true : false;

		$exp = strtotime($msg['validity']) < $time;

		echo '<div class="panel panel-info printview">';
		echo '<div class="panel-body';
		echo ($exp) ? ' bg-danger' : '';
		echo '">';

		echo '<div class="media">';

		if (isset($imgs[$msg['id']]))
		{
			echo '<div class="media-left">';
			echo '<a href="' . generate_url('messages', ['id' => $msg['id']]) . '">';
			echo '<img class="media-object" src="' . $s3_img_url . $imgs[$msg['id']] . '" width="150">';
			echo '</a>';
			echo '</div>';
		}

		echo '<div class="media-body">';
		echo '<h3 class="media-heading">';
		echo aphp('messages', ['id' => $msg['id']], $type_str . ': ' . $msg['content']);
		echo ($exp) ? ' <small><span class="text-danger">Vervallen</span></small>' : '';
		echo '</h3>';

		echo htmlspecialchars($msg['Description'], ENT_QUOTES);
		echo '</div>';
		echo '</div>';

		echo '</div>';

		echo '<div class="panel-footer">';
		echo '<p><i class="fa fa-user"></i> ' . link_user($msg['id_user']);
		echo ($msg['postcode']) ? ', postcode: ' . $msg['postcode'] : '';

		if ($s_admin || $sf_owner)
		{
			echo '<span class="inline-buttons pull-right hidden-xs">';
			echo aphp('messages', ['edit' => $msg['id']], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
			echo aphp('messages', ['del' => $msg['id']], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
			echo '</span>';
		}
		echo '</p>';
		echo '</div>';

		echo '</div>';
	}
}

if (!$recent)
{
	$pagination->render();
}

if ($inline)
{
	echo '</div></div>';
}
else if ($v_list)
{
	if (($s_admin || $s_owner) && count($messages))
	{
		$extend_options = [
			'7'		=> '1 week',
			'14'	=> '2 weken',
			'30'	=> '1 maand',
			'60'	=> '2 maanden',
			'180'	=> '6 maanden',
			'365'	=> '1 jaar',
			'730'	=> '2 jaar',
			'1825'	=> '5 jaar',
		];

		echo '<div class="panel panel-default" id="actions">';
		echo '<div class="panel-heading">';
		echo '<span class="btn btn-default" id="select_all">Selecteer alle</span>&nbsp;';
		echo '<span class="btn btn-default" id="deselect_all">De-selecteer alle</span>';
		echo '</div></div>';

		echo '<h3>Bulk acties met geselecteerd vraag en aanbod</h3>';

		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';

		echo '<form method="post" class="form-horizontal" id="bulk">';

		echo '<ul class="nav nav-tabs" role="tablist">';
		echo '<li class="active"><a href="#extend_tab" data-toggle="tab">Verlengen</a></li>';
		echo '<li><a href="#access_tab" data-toggle="tab">Zichtbaarheid</a><li>';
		echo '</ul>';

		echo '<div class="tab-content">';

		echo '<div role="tabpanel" class="tab-pane active" id="extend_tab">';
		echo '<h3>Vraag en aanbod verlengen</h3>';

		echo '<div class="form-group">';
		echo '<label for="extend" class="col-sm-2 control-label">Verlengen met</label>';
		echo '<div class="col-sm-10">';
		echo '<select name="extend" id="extend" class="form-control">';
		render_select_options($extend_options, '30');
		echo "</select>";
		echo '</div>';
		echo '</div>';

		echo '<input type="submit" value="Verlengen" name="extend_submit" class="btn btn-primary">';

		echo '</div>';

		echo '<div role="tabpanel" class="tab-pane" id="access_tab">';
		echo '<h3>Zichtbaarheid instellen</h3>';

		echo $access_control->get_radio_buttons(false, false, 'admin');

		echo '<input type="submit" value="Aanpassen" name="access_submit" class="btn btn-primary">';

		echo '</div>';
		echo '</div>';

		echo '<div class="clearfix"></div>';
		echo '</div>';

		echo '</form>';

		echo '</div></div>';
	}

	include $rootpath . 'includes/inc_footer.php';
}
else if ($v_extended)
{
	include $rootpath . 'includes/inc_footer.php';
}

function cancel($id = null)
{
	global $uid, $view_messages;

	if ($id)
	{
		$params = ['id' => $id];
	}
	else
	{
		$params = ['view' => $view_messages];		

		if ($uid)
		{
			$params['uid'] = $uid;
		}
	}

	header('Location: ' . generate_url('messages', $params));
	exit;
}

