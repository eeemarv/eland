<?php

$page_access = 'guest';
$allow_guest_post = true;
require_once __DIR__ . '/include/web.php';

$id = $_GET['id'] ?? false;
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = isset($_GET['add']) ? true : false;
$uid = $_GET['uid'] ?? false;
$type = $_GET['type'] ?? false;
$submit = isset($_POST['zend']) ? true : false;
$orderby = $_GET['orderby'] ?? 'm.cdate';
$asc = $_GET['asc'] ?? 0;
$recent = isset($_GET['recent']) ? true : false;
$limit = $_GET['limit'] ?? 25;
$start = $_GET['start'] ?? 0;
$filter = $_GET['f'] ?? [];
$img = isset($_GET['img']) ? true : false;
$insert_img = isset($_GET['insert_img']) ? true : false;
$img_del = $_GET['img_del'] ?? false;
$images = $_FILES['images'] ?? false;
$mail = isset($_POST['mail']) ? true : false;
$selected_msgs = (isset($_POST['sel']) && $_POST['sel'] != '') ? explode(',', $_POST['sel']) : [];
$extend_submit = isset($_POST['extend_submit']) ? true : false;
$extend = $_POST['extend'] ?? false;
$access_submit = isset($_POST['access_submit']) ? true : false;

$access = $app['access_control']->get_post_value();

if ($post && $s_guest && ($add || $edit || $del || $img || $img_del || $images
	|| $extend_submit || $access_submit || $extend || $access))
{
	$app['alert']->error('Geen toegang als gast tot deze actie');
	cancel($id);
}

if (!$post)
{
	$extend = $_GET['extend'] ?? false;
}

/*
 * bulk actions (set access or validity)
 */
if ($post & (($extend_submit && $extend) || ($access_submit && $access)) & ($s_admin || $s_user))
{
	if (!is_array($selected_msgs) || !count($selected_msgs))
	{
		$app['alert']->error('Selecteer ten minste één vraag of aanbod voor deze actie.');
		cancel();
	}

	if (!count($selected_msgs))
	{
		$errors[] = 'Selecteer ten minste één vraag of aanbod voor deze actie.';
	}

	if ($error_token = $app['form_token']->get_error())
	{
		$errors[] = $error_token;
	}

	$validity_ary = [];

	$rows = $app['db']->executeQuery('select id_user, id, content, validity from messages where id in (?)',
			[$selected_msgs], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($rows as $row)
	{
		if (!$s_admin && $s_user && ($row['id_user'] != $s_id))
		{
			$errors[] = 'Je bent niet de eigenaar van vraag of aanbod ' . $row['content'] . ' ( ' . $row['id'] . ')';
			cancel();
		}

		$validity_ary[$row['id']] = $row['validity'];
	}

	if ($extend_submit && !count($errors))
	{
		foreach ($validity_ary as $id => $validity)
		{
			$validity = gmdate('Y-m-d H:i:s', strtotime($validity) + (86400 * $extend));

			$m = [
				'validity'		=> $validity,
				'mdate'			=> gmdate('Y-m-d H:i:s'),
				'exp_user_warn'	=> 'f',
			];

			if (!$app['db']->update('messages', $m, ['id' => $id]))
			{
				$app['alert']->error('Fout: ' . $row['content'] . ' is niet verlengd.');
				cancel();
			}
		}
		if (count($validity_ary) > 1)
		{
			$app['alert']->success('De berichten zijn verlengd.');
		}
		else
		{
			$app['alert']->success('Het bericht is verlengd.');
		}

		cancel();
	}

	if ($access_submit && !count($errors))
	{
		$access_error = $app['access_control']->get_post_error();

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

			$app['db']->beginTransaction();

			try
			{
				foreach ($validity_ary as $id => $validity)
				{
					$app['db']->update('messages', $m, ['id' => $id]);
				}

				$app['db']->commit();

				if (count($selected_msgs) > 1)
				{
					$app['alert']->success('De berichten zijn aangepast.');
				}
				else
				{
					$app['alert']->success('Het bericht is aangepast.');
				}

				cancel();
			}
			catch(Exception $e)
			{
				$app['db']->rollback();
				throw $e;
				$app['alert']->error('Fout bij het opslaan.');
				cancel();
			}
		}

		$app['alert']->error($errors);
	}
}

/*
 * fetch message
 */
if ($id || $edit || $del)
{
	$id = $id ?: ($edit ?: $del);

	$message = $app['db']->fetchAssoc('select m.*,
			c.id as cid,
			c.fullname as catname
		FROM messages m, categories c
		WHERE m.id = ?
			AND c.id = m.id_category', [$id]);

	if (!$message)
	{
		$app['alert']->error('Bericht niet gevonden.');
		cancel();
	}

	$s_owner = (!$s_guest && $s_group_self && $s_id == $message['id_user'] && $message['id_user']) ? true : false;

	if ($message['local'] && $s_guest)
	{
		$app['alert']->error('Je hebt geen toegang tot dit bericht.');
		cancel();
	}

	$ow_type = $message['msg_type'] ? 'aanbod' : 'vraag';
	$ow_type_this = $message['msg_type'] ? 'dit aanbod' : 'deze vraag';
	$ow_type_the = $message['msg_type'] ? 'het aanbod' : 'de vraag';
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
		$app['alert']->error('Je hebt onvoldoende rechten om ' . $ow_type_this . ' te verlengen.');
		cancel($id);
	}

	$validity = gmdate('Y-m-d H:i:s', strtotime($message['validity']) + (86400 * $extend));

	$m = [
		'validity'		=> $validity,
		'mdate'			=> gmdate('Y-m-d H:i:s'),
		'exp_user_warn'	=> 'f',
	];

	if (!$app['db']->update('messages', $m, ['id' => $id]))
	{
		$app['alert']->error('Fout: ' . $ow_type_the . ' is niet verlengd.');
		cancel($id);
	}

	$app['alert']->success($ow_type_uc_the . ' is verlengd.');
	cancel($id);
}

/**
 * post images
 */
if ($post && $img && $images && !$s_guest)
{
	$ret_ary = [];

	if ($id)
	{
		if (!$s_owner && !$s_admin)
		{
			$ret_ary[] = ['error' => 'Je hebt onvoldoende rechten om een afbeelding op te laden voor dit vraag of aanbod bericht.'];
		}
	}

	if (!$insert_img)
	{
		$form_token = $_GET['form_token'] ?? false;

		if (!$form_token)
		{
			$ret_ary[] = ['error' => 'Geen form token gedefiniëerd.'];
		}
		else if (!$app['predis']->get('form_token_' . $form_token))
		{
			$ret_ary[] = ['error' => 'Formulier verlopen of ongeldig.'];
		}
	}

	if (count($ret_ary))
	{
		$images = [];
	}

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

		$exif = exif_read_data($tmpfile);

		$tmpfile2 = tempnam(sys_get_temp_dir(), 'img');

		$imagine = new Imagine\Imagick\Imagine();

		$image = $imagine->open($tmpfile);

		$orientation = $exif['COMPUTED']['Orientation'] ?? false;

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

		// if no msg id available then we get the probable next id. If it doesn't match
		// when the msg is posted then the file will get renamed.

		if (!$id)
		{
			$id = $app['db']->fetchColumn('select max(id) from messages');
			$id++;
		}

		$filename = $app['this_group']->get_schema() . '_m_' . $id . '_';
		$filename .= sha1($filename . microtime()) . '.jpg';

		$err = $app['s3']->img_upload($filename, $tmpfile2);

		if ($err)
		{
			$app['monolog']->error('Upload fail : ' . $err);

			$ret_ary = [['error' => 'Opladen mislukt.']];
			break;
		}
		else
		{
			if ($insert_img)
			{
				$app['db']->insert('msgpictures', [
					'msgid'			=> $id,
					'"PictureFile"'	=> $filename]);

				$app['monolog']->info('Message-Picture ' . $filename . ' uploaded and inserted in db.');
			}
			else
			{
				$app['monolog']->info('Message-Picture ' . $filename . ' uploaded, not (yet) inserted in db.');
			}

			unlink($tmpfile);

			$ret_ary[] = ['filename' => $filename];
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

/**
 * Delete all images
 */

if ($img_del == 'all' && $id && $post)
{
	if (!($s_owner || $s_admin))
	{
		$app['alert']->error('Je hebt onvoldoende rechten om afbeeldingen te verwijderen voor ' . $ow_type_this);
	}

	$app['db']->delete('msgpictures', ['msgid' => $id]);

	$app['alert']->success('De afbeeldingen voor ' . $ow_type_this . ' zijn verwijderd.');

	cancel($id);
}

/*
 * delete an image
 */
if ($img_del && $post && ctype_digit((string) $img_del))
{
	if (!($msg = $app['db']->fetchAssoc('select m.id_user, p."PictureFile"
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

	$app['db']->delete('msgpictures', ['id' => $img_del]);

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
		$app['alert']->error('Je kan geen afbeeldingen verwijderen voor ' . $ow_type_this);
		cancel($id);
	}

	$images = [];

	$st = $app['db']->prepare('select id, "PictureFile" from msgpictures where msgid = ?');
	$st->bindValue(1, $id);
	$st->execute();

	while ($row = $st->fetch())
	{
		$images[$row['id']] = $row['PictureFile'];
	}

	if (!count($images))
	{
		$app['alert']->error($ow_type_uc_the . ' heeft geen afbeeldingen.');
		cancel($id);
	}

	$str_this_ow = $ow_type . ' "' . aphp('messages', ['id' => $id], $message['content']) . '"';
	$h1 = 'Afbeeldingen verwijderen voor ' . $str_this_ow;
	$fa = 'newspaper-o';

	$app['assets']->add('msg_img_del.js');

	include __DIR__ . '/include/header.php';

	if ($s_admin)
	{
		echo 'Gebruiker: ' . link_user($message['id_user']);
	}

	echo '<div class="row">';

	foreach ($images as $img_id => $file)
	{
		$a_img = $app['s3_img_url'] . $file;

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

	include __DIR__ . '/include/footer.php';

	exit;
}

/*
 * send email
 */
if ($mail && $post && $id)
{
	$content = $_POST['content'];
	$cc = $_POST['cc'];

	$user = $app['user_cache']->get($message['id_user']);

	if (!$s_admin && !in_array($user['status'], [1, 2]))
	{
		$app['alert']->error('Je hebt geen rechten om een bericht naar een niet-actieve gebruiker te sturen');
		cancel();
	}

	if ($s_master)
	{
		$app['alert']->error('Het master account kan geen berichten versturen.');
		cancel();
	}

	if (!$s_schema)
	{
		$app['alert']->error('Je hebt onvoldoende rechten om een E-mail bericht te versturen.');
		cancel();
	}

	if (!$content)
	{
		$app['alert']->error('Fout: leeg bericht. E-mail niet verzonden.');
		cancel($id);
	}

	$contacts = $app['db']->fetchAll('select c.value, tc.abbrev
		from ' . $s_schema . '.contact c, ' . $s_schema . '.type_contact tc
		where c.flag_public >= ?
			and c.id_user = ?
			and c.id_type_contact = tc.id', [$access_ary[$user['accountrole']], $s_id]);

	$message['type'] = $message['msg_type'] ? 'offer' : 'want';

	$vars = [
		'group'		=> [
			'tag'	=> $app['config']->get('systemtag'),
			'name'	=> $app['config']->get('systemname'),
		],
		'to_user'		=> link_user($user, false, false),
		'to_username'	=> $user['name'],
		'from_user'		=> link_user($session_user, $s_schema, false),
		'from_username'	=> $session_user['name'],
		'to_group'		=> $s_group_self ? '' : $app['config']->get('systemname'),
		'from_group'	=> $s_group_self ? '' : $app['config']->get('systemname', $s_schema),
		'contacts'		=> $contacts,
		'msg_text'		=> $content,
		'message'		=> $message,
		'login_url'		=> $app['base_url'].'/login.php',
	];

	$app['queue.mail']->queue([
		'to'		=> $user['id'],
		'reply_to'	=> $s_schema . '.' . $s_id,
		'template'	=> 'message',
		'vars'		=> $vars,
	], 600);


	if ($cc)
	{
		$app['queue.mail']->queue([
			'to'		=> $s_schema . '.' . $s_id,
			'template'	=> 'message_copy',
			'vars'		=> $vars,
		], 600);
	}

	$app['alert']->success('Mail verzonden.');

	cancel($id);
}

/*
 * delete message
 */
if ($del)
{
	if (!($s_owner || $s_admin))
	{
		$app['alert']->error('Je hebt onvoldoende rechten om ' . $ow_type_this . ' te verwijderen.');
		cancel($del);
	}

	if($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
		}

		$app['db']->delete('msgpictures', ['msgid' => $del]);

		if ($app['db']->delete('messages', ['id' => $del]))
		{
			$column = 'stat_msgs_';
			$column .= ($message['msg_type']) ? 'offers' : 'wanted';

			$app['db']->executeUpdate('update categories
				set ' . $column . ' = ' . $column . ' - 1
				where id = ?', [$message['id_category']]);

			$app['alert']->success(ucfirst($ow_type_this) . ' is verwijderd.');
			cancel();
		}

		$app['alert']->error(ucfirst($ow_type_this) . ' is niet verwijderd.');
	}

	$h1 = ucfirst($ow_type_this) . ' ';
	$h1 .= aphp('messages', ['id' => $del], $message['content']);
	$h1 .= ' verwijderen?';
	$fa = 'newspaper-o';

	include __DIR__ . '/include/header.php';

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

	if ($count_interlets_groups)
	{
		echo '<dt>Zichtbaarheid</dt>';
		echo '<dd>';
		echo $app['access_control']->get_label($message['local'] ? 'users' : 'interlets');
		echo '</dd>';
	}

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
	echo $app['form_token']->get_hidden_input();
	echo '</form></p>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/*
 * edit - add
 */
if (($edit || $add))
{
	if (!($s_admin || $s_user) && $add)
	{
		$app['alert']->error('Je hebt onvoldoende rechten om een vraag of aanbod toe te voegen.');
		cancel();
	}

	if (!($s_admin || $s_owner) && $edit)
	{
		$app['alert']->error('Je hebt onvoldoende rechten om ' . $ow_type_this . ' aan te passen.');
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
			[$user_letscode] = explode(' ', trim($_POST['user_letscode']));
			$user_letscode = trim($user_letscode);
			$user = $app['db']->fetchAssoc('select *
				from users
				where letscode = ?
					and status in (1, 2)', [$user_letscode]);
			if (!$user)
			{
				$errors[] = 'Ongeldige letscode. ' . $user_letscode;
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
		];

		$deleted_images = isset($_POST['deleted_images']) && $edit ? $_POST['deleted_images'] : [];
		$uploaded_images = $_POST['uploaded_images'] ?? [];

		if ($count_interlets_groups)
		{
			$access_error = $app['access_control']->get_post_error();

			if ($access_error)
			{
				$errors[] = $access_error;
			}

			$msg['local'] = $app['access_control']->get_post_value() == 2 ? 0 : 1;
		}
		else if ($add)
		{
			$msg['local'] = 1;
		}

		if (!ctype_digit((string) $msg['amount']) && $msg['amount'] != '')
		{
			$errors[] = 'De (richt)prijs in ' . $app['config']->get('currency') . ' moet nul of een positief getal zijn.';
		}

		if (!$msg['id_category'])
		{
			$errors[] = 'Geieve een categorie te selecteren.';
		}
		else if(!$app['db']->fetchColumn('select id from categories where id = ?', [$msg['id_category']]))
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

		if(!($app['db']->fetchColumn('select id from users where id = ? and status <> 0', [$msg['id_user']])))
		{
			$errors[] = 'Gebruiker bestaat niet!';
		}

		if ($error_form = $app['form_token']->get_error())
		{
			$errors[] = $error_form;
		}

		if (count($errors))
		{
			$app['alert']->error($errors);
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

			if ($app['db']->insert('messages', $msg))
			{
				$id = $app['db']->lastInsertId('messages_id_seq');

				$stat_column = 'stat_msgs_';
				$stat_column .= $msg['msg_type'] ? 'offers' : 'wanted';

				$app['db']->executeUpdate('update categories set ' . $stat_column . ' = ' . $stat_column . ' + 1 where id = ?', [$msg['id_category']]);

				if (count($uploaded_images))
				{
					foreach ($uploaded_images as $img)
					{
						$img_errors = [];

						[$sch, $img_type, $msgid, $hash] = explode('_', $img);

						if ($sch != $app['this_group']->get_schema())
						{
							$img_errors[] = 'Schema stemt niet overeen voor afbeelding ' . $img;
						}

						if ($img_type != 'm')
						{
							$img_errors[] = 'Type stemt niet overeen voor afbeelding ' . $img;
						}

						if (count($img_errors))
						{
							$app['alert']->error($img_errors);

							continue;
						}

						if ($msgid == $id)
						{
							if ($app['db']->insert('msgpictures', [
								'"PictureFile"' => $img,
								'msgid'			=> $id,
							]))
							{
								$app['monolog']->info('message-picture ' . $img . ' inserted in db.');
							}
							else
							{
								$app['monolog']->error('error message-picture ' . $img . ' not inserted in db.');
							}

							continue;
						}

						$new_filename = $app['this_group']->get_schema() . '_m_' . $id . '_';
						$new_filename .= sha1($new_filename . microtime()) . '.jpg';

						$err = $app['s3']->img_copy($img, $new_filename);

						if (isset($err))
						{
							$app['monolog']->error('message-picture renaming and storing in db ' . $img . ' not succeeded. ' . $err);
						}
						else
						{
							$app['monolog']->info('renamed ' . $img . ' to ' . $new_filename);

							if ($app['db']->insert('msgpictures', [
								'"PictureFile"'		=> $new_filename,
								'msgid'				=> $id,
							]))
							{
								$app['monolog']->info('message-picture ' . $new_filename . ' inserted in db.');
							}
							else
							{
								$app['monolog']->error('error: message-picture ' . $new_filename . ' not inserted in db.');
							}
						}
					}
				}

				$app['alert']->success('Nieuw vraag of aanbod toegevoegd.');

				cancel($id);
			}
			else
			{
				$app['alert']->error('Fout bij het opslaan van vraag of aanbod.');
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

			unset($msg['vtime']);

			if (empty($msg['amount']))
			{
				unset($msg['amount']);
			}

			$app['db']->beginTransaction();

			try
			{
				$app['db']->update('messages', $msg, ['id' => $edit]);

				if ($msg['msg_type'] != $message['msg_type'] || $msg['id_category'] != $message['id_category'])
				{
					$column = 'stat_msgs_';
					$column .= ($message['msg_type']) ? 'offers' : 'wanted';

					$app['db']->executeUpdate('update categories
						set ' . $column . ' = ' . $column . ' - 1
						where id = ?', [$message['id_category']]);

					$column = 'stat_msgs_';
					$column .= ($msg['msg_type']) ? 'offers' : 'wanted';

					$app['db']->executeUpdate('update categories
						set ' . $column . ' = ' . $column . ' + 1
						where id = ?', [$msg['id_category']]);
				}

				if (count($deleted_images))
				{
					foreach ($deleted_images as $img)
					{
						if ($app['db']->delete('msgpictures', [
							'msgid'		=> $edit,
							'"PictureFile"'	=> $img,
						]))
						{
							$app['monolog']->info('message-picture ' . $img . ' deleted from db.');
						}
					}
				}

				if (count($uploaded_images))
				{
					foreach ($uploaded_images as $img)
					{
						$img_errors = [];

						[$sch, $img_type, $msgid, $hash] = explode('_', $img);

						if ($sch != $app['this_group']->get_schema())
						{
							$img_errors[] = 'Schema stemt niet overeen voor afbeelding ' . $img;
						}

						if ($img_type != 'm')
						{
							$img_errors[] = 'Type stemt niet overeen voor afbeelding ' . $img;
						}

						if ($msgid != $edit)
						{
							$img_errors[] = 'Id stemt niet overeen voor afbeelding ' . $img;
						}

						if (count($img_errors))
						{
							$app['alert']->error($img_errors);

							continue;
						}

						if ($app['db']->insert('msgpictures', [
							'"PictureFile"' => $img,
							'msgid'			=> $edit,
						]))
						{
							$app['monolog']->info('message-picture ' . $img . ' inserted in db.');
						}
						else
						{
							$app['monolog']->error('error message-picture ' . $img . ' not inserted in db.');
						}
					}
				}

				$app['db']->commit();
				$app['alert']->success('Vraag/aanbod aangepast');
				cancel($edit);
			}
			catch(Exception $e)
			{
				$app['db']->rollback();
				throw $e;
				exit;
			}
		}
		else
		{
			$app['alert']->error('Fout: onbepaalde actie.');
			cancel();
		}

		$msg['description'] = $msg['"Description"'];

		$images = $edit ? $app['db']->fetchAll('select * from msgpictures where msgid = ?', [$edit]) : [];

		if (count($deleted_images))
		{
			foreach ($deleted_images as $del_img)
			{
				foreach ($images as $key => $img)
				{
					if ($img['PictureFile'] == $del_img)
					{
						unset($images[$key]);
					}
				}
			}
		}

		if (count($uploaded_images))
		{
			foreach ($uploaded_images as $upl_img)
			{
				$images[] = ['PictureFile' => $upl_img];
			}
		}
	}
	else if ($edit)
	{
		$msg =  $app['db']->fetchAssoc('select m.*,
			m."Description" as description
			from messages m
			where m.id = ?', [$edit]);
		$msg['description'] = $msg['Description'];
		unset($msg['Description']);

		$rev = round((strtotime($msg['validity']) - time()) / (86400));
		$msg['validity'] = ($rev < 1) ? 0 : $rev;

		$user = $app['user_cache']->get($msg['id_user']);

		$user_letscode = $user['letscode'] . ' ' . $user['name'];

		$images = $app['db']->fetchAll('select * from msgpictures where msgid = ?', [$edit]);
	}
	else if ($add)
	{
		$msg = [
			'validity'		=> $app['config']->get('msgs_days_default'),
			'content'		=> '',
			'description'	=> '',
			'msg_type'		=> 'none',
			'id_user'		=> $s_master ? 0 : $s_id,
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
			$user = $app['user_cache']->get($uid);

			$user_letscode = $user['letscode'] . ' ' . $user['name'];
		}

		$images = [];
	}

	$cat_list = ['' => ''];

	$rs = $app['db']->prepare('SELECT id, fullname  FROM categories WHERE leafnote=1 order by fullname');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$cat_list[$row['id']] = $row['fullname'];
	}

	array_walk($msg, function(&$value, $key){ $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); });

	if ($s_admin)
	{
		$app['assets']->add(['typeahead', 'typeahead.js']);
	}

	$app['assets']->add(['fileupload', 'msg_edit.js', 'access_input_cache.js']);

	$h1 = ($add) ? 'Nieuw Vraag of Aanbod toevoegen' : 'Vraag of Aanbod aanpassen';
	$fa = 'newspaper-o';

	include __DIR__ . '/include/header.php';

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
		echo 'data-typeahead="' . $app['typeahead']->get('users_active') . '" ';
		echo 'data-newuserdays="' . $app['config']->get('newuserdays') . '" ';
		echo 'value="' . $user_letscode . '" required>';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo '<label for="msg_type" class="col-sm-2 control-label">&nbsp;</label>';
	echo '<div class="col-sm-10">';

	echo get_radio(['1' => 'Aanbod', '0' => 'Vraag'], 'msg_type', $msg['msg_type'], true);

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
	echo get_select_options($cat_list, $msg['id_category']);
	echo "</select>";
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="validity" class="col-sm-2 control-label">Geldigheid in dagen</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="number" class="form-control" id="validity" name="validity" min="1" ';
	echo 'value="';
	echo $msg['validity'];
	echo '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="amount" class="col-sm-2 control-label">Aantal ' . $app['config']->get('currency') . '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="number" class="form-control" id="amount" name="amount" min="0" ';
	echo 'value="';
	echo $msg['amount'];
	echo '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="units" class="col-sm-2 control-label">Per (uur, stuk, ...)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="units" name="units" ';
	echo 'value="';
	echo $msg['units'];
	echo '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="fileupload" class="col-sm-2 control-label">Afbeeldingen</label>';
	echo '<div class="col-sm-10">';

	echo '<div class="row">';

	echo '<div class="col-sm-3 col-md-2 thumbnail-col hidden" id="thumbnail_model" ';
	echo 'data-s3-url="';
	echo $app['s3_img_url'];
	echo '">';
	echo '<div class="thumbnail">';
	echo '<img src="" alt="afbeelding">';
	echo '<div class="caption">';

	echo '<p><span class="btn btn-danger img-delete" role="button">';
	echo '<i class="fa fa-times"></i></span></p>';
	echo '</div>';
	echo '</div>';
	echo '</div>';

	foreach ($images as $img)
	{
		echo '<div class="col-sm-3 col-md-2 thumbnail-col">';
		echo '<div class="thumbnail">';
		echo '<img src="';
		echo $app['s3_img_url'] . $img['PictureFile'];
		echo '" alt="afbeelding">';
		echo '<div class="caption">';

		echo '<p><span class="btn btn-danger img-delete" role="button">';
		echo '<i class="fa fa-times"></i></span></p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	echo '</div>';

	$upload_img_param = [
		'img'	=> 1,
		'form_token' => $upload_img_param['form_token'] = $app['form_token']->get(),
	];

	if ($edit)
	{
		$upload_img_param['id'] = $id;
	}

	echo '<span class="btn btn-default fileinput-button">';
	echo '<i class="fa fa-plus" id="img_plus"></i> Opladen';
	echo '<input id="fileupload" type="file" name="images[]" ';
	echo 'data-url="';
	echo generate_url('messages', $upload_img_param);
	echo '" ';
	echo 'data-data-type="json" data-auto-upload="true" ';
	echo 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
	echo 'data-max-file-size="999000" ';
	echo 'multiple></span>&nbsp;';

	echo '<p><small>Afbeeldingen moeten in het jpg/jpeg formaat zijn. Je kan ook afbeeldingen hierheen ';
	echo 'verslepen.</small></p>';
	echo '</div>';
	echo '</div>';

	if ($count_interlets_groups)
	{
		$access_value = $edit ? ($msg['local'] ? 'users' : 'interlets') : false;

		echo $app['access_control']->get_radio_buttons('messages', $access_value, 'admin');
	}

	$btn = ($edit) ? 'primary' : 'success';

	echo aphp('messages', ['id' => $id], 'Annuleren', 'btn btn-default'). '&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-' . $btn . '">';
	echo $app['form_token']->get_hidden_input();

	if (isset($uploaded_images) && count($uploaded_images))
	{
		foreach ($uploaded_images as $img)
		{
			echo '<input type="hidden" name="uploaded_images[]" value="' . $img . '">';
		}
	}

	if (isset($deleted_images) && count($deleted_images))
	{
		foreach ($deleted_images as $img)
		{
			echo '<input type="hidden" name="deleted_images[]" value="' . $img . '">';
		}
	}

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * show a message
 */
if ($id)
{
	$cc = ($post) ? $cc : 1;

	$user = $app['user_cache']->get($message['id_user']);

	$to = $app['db']->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', [$user['id']]);

	$mail_to = $app['mailaddr']->get($user['id']);
	$mail_from = ($s_schema && !$s_master && !$s_elas_guest) ? $app['mailaddr']->get($s_schema . '.' . $s_id) : [];

	$balance = $user['saldo'];

	$images = [];

	$st = $app['db']->prepare('select id, "PictureFile" from msgpictures where msgid = ?');
	$st->bindValue(1, $id);
	$st->execute();

	while ($row = $st->fetch())
	{
		$images[$row['id']] = $row['PictureFile'];
	}

	$and_local = ($s_guest) ? ' and local = \'f\' ' : '';

	$prev = $app['db']->fetchColumn('select id
		from messages
		where id > ?
		' . $and_local . '
		order by id asc
		limit 1', [$id]);

	$next = $app['db']->fetchColumn('select id
		from messages
		where id < ?
		' . $and_local . '
		order by id desc
		limit 1', [$id]);

	$title = $message['content'];

	$contacts = $app['db']->fetchAll('select c.*, tc.abbrev
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and c.flag_public = 1', [$user['id']]);

	$app['assets']->add(['leaflet', 'jssor', 'msg.js']);

	if ($s_admin || $s_owner)
	{
		$app['assets']->add(['fileupload', 'msg_img.js']);
	}

	if ($s_admin || $s_owner)
	{
		$top_buttons .= aphp('messages', ['edit' => $id], 'Aanpassen', 'btn btn-primary', $ow_type_uc . ' aanpassen', 'pencil', true);
		$top_buttons .= aphp('messages', ['del' => $id], 'Verwijderen', 'btn btn-danger', $ow_type_uc . ' verwijderen', 'times', true);
	}

	if ($message['msg_type'] == 1
		&& ($s_admin || (!$s_owner
		&& $user['status'] != 7
		&& !($s_guest && $s_group_self))))
	{
			$tus = ['add' => 1, 'mid' => $id];

			if (!$s_group_self)
			{
				$tus['tus'] = $app['this_group']->get_schema();
			}

			$top_buttons .= aphp('transactions', $tus, 'Transactie',
				'btn btn-warning', 'Transactie voor dit aanbod',
				'exchange', true, false, $s_schema);
	}

	$top_buttons_right = '<span class="btn-group" role="group">';

	$prev_url = $prev ? generate_url('messages', ['id' => $prev]) : '';
	$next_url = $next ? generate_url('messages', ['id' => $next]) : '';

	$top_buttons_right .= btn_item_nav($prev_url, false, false);
	$top_buttons_right .= btn_item_nav($next_url, true, true);
	$top_buttons_right .= aphp('messages', ['view' => $view_messages], '', 'btn btn-default', 'Alle vraag en aanbod', 'newspaper-o');
	$top_buttons_right .= '</span>';

	$h1 = $ow_type_uc;
	$h1 .= ': ' . htmlspecialchars($message['content'], ENT_QUOTES);
	$h1 .= strtotime($message['validity']) < time() ? ' <small><span class="text-danger">Vervallen</span></small>' : '';
	$fa = 'newspaper-o';

	include __DIR__ . '/include/header.php';

	if ($message['cid'])
	{
		echo '<p>Categorie: ';
		echo '<a href="';
		echo generate_url('messages', ['cid' => $message['cid'], 'view' => $view_messages]);
		echo '">';
		echo $message['catname'];
		echo '</a></p>';
	}

	echo '<div class="row">';

	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-body">';

	echo '<div id="no_images" class="text-center center-body" style="display: none;">';
	echo '<i class="fa fa-image fa-5x"></i> ';
	echo '<p>Er zijn geen afbeeldingen voor ' . $ow_type_this . '</p>';
	echo '</div>';

	echo '<div id="images_con" ';
	echo 'data-bucket-url="' . $app['s3_img_url'] . '" ';
	echo 'data-images="' . implode(',', $images) . '">';
	echo '</div>';

	echo '</div>';

	if ($s_admin || $s_owner)
	{
		echo '<div class="panel-footer"><span class="btn btn-success fileinput-button">';
		echo '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
		echo '<input id="fileupload" type="file" name="images[]" ';
		echo 'data-url="';
		echo generate_url('messages', ['img' => 1, 'id' => $id, 'insert_img' => 1]);
		echo '" ';
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
	$units = $message['units'] ? ' per ' . $message['units'] : '';
	echo empty($message['amount']) ? 'niet opgegeven.' : $message['amount'] . ' ' . $app['config']->get('currency') . $units;
	echo '</dd>';

	echo '<dt>Van gebruiker: ';
	echo '</dt>';
	echo '<dd>';
	echo link_user($user);
	echo ' (saldo: <span class="label label-info">' . $balance . '</span> ' .$app['config']->get('currency') . ')';
	echo '</dd>';

	echo '<dt>Plaats</dt>';
	echo '<dd>' . $user['postcode'] . '</dd>';

	echo '<dt>Aangemaakt op</dt>';
	echo '<dd>' . $app['date_format']->get($message['cdate'], 'day') . '</dd>';

	echo '<dt>Geldig tot</dt>';
	echo '<dd>' . $app['date_format']->get($message['validity'], 'day') . '</dd>';

	if ($s_admin || $s_owner)
	{
		echo '<dt>Verlengen</dt>';
		echo '<dd>' . aphp('messages', ['id' => $id, 'extend' => 30], '1 maand', 'btn btn-default btn-xs') . '&nbsp;';
		echo aphp('messages', ['id' => $id, 'extend' => 180], '6 maanden', 'btn btn-default btn-xs') . '&nbsp;';
		echo aphp('messages', ['id' => $id, 'extend' => 365], '1 jaar', 'btn btn-default btn-xs') . '</dd>';
	}

	if ($count_interlets_groups)
	{
		echo '<dt>Zichtbaarheid</dt>';
		echo '<dd>';
		echo  $app['access_control']->get_label($message['local'] ? 'users' : 'interlets');
		echo '</dd>';
	}

	echo '</dl>';

	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '</div>';

	echo '<div id="contacts" ';
	echo 'data-url="' . $rootpath . 'contacts.php?inline=1&uid=' . $message['id_user'];
	echo '&' . http_build_query(get_session_query_param()) . '"></div>';

// response form

	if ($s_elas_guest)
	{
		$placeholder = 'Als eLAS gast kan je niet het E-mail formulier gebruiken.';
	}
	else if ($s_owner)
	{
		$placeholder = 'Je kan geen reacties op je eigen berichten sturen.';
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
	echo $disabled ? ' disabled' : '';
	echo '>';
	echo $content ?? '';
	echo '</textarea>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<input type="checkbox" name="cc"';
	echo $cc ? ' checked="checked"' : '';
	echo ' value="1" >Stuur een kopie naar mijzelf';
	echo '</div>';
	echo '</div>';

	echo '<input type="submit" name="mail" value="Versturen" class="btn btn-default"';
	echo $disabled ? ' disabled' : '';
	echo '>';
	echo '</form>';

	echo '</div>';
	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
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

$v_list = ($view === 'list' || $inline) && !$recent ? true : false;
$v_extended = $view === 'extended' && !$inline || $recent ? true : false;
$v_map = $view === 'map' && !($inline || $recent) ? true : false;

$params = [
	'view'		=> $view,
	'orderby'	=> $orderby,
	'asc'		=> $asc,
	'limit'		=> $limit,
	'start'		=> $start,
];

$params_sql = $where_sql = $ustatus_sql = [];
$filter_en = isset($filter['s']);

if ($uid)
{
	$user = $app['user_cache']->get($uid);

	$where_sql[] = 'u.id = ?';
	$params_sql[] = $uid;
	$params['uid'] = $uid;

	$filter['fcode'] = link_user($user, false, false);
}

if ($type)
{
	if ($type === 'wants')
	{
		$where_sql[] = 'm.msg_type = 0';
		$filter['type']['want'] = 'on';
	}
	else if ($type === 'offers')
	{
		$where_sql[] = 'm.msg_type = 1';
		$filter['type']['offer'] = 'on';
	}
}

if ($filter_en)
{
	if (!$uid)
	{
		if (isset($filter['fcode']) && $filter['fcode']);
		{
			[$fcode] = explode(' ', trim($filter['fcode']));
			$fcode = trim($fcode);

			if ($fcode)
			{
				$fuid = $app['db']->fetchColumn('select id from users where letscode = ?', [$fcode]);

				if ($fuid)
				{
					$where_sql[] = 'u.id = ?';
					$params_sql[] = $fuid;

					$filter['fcode'] = link_user($fuid, false, false);
				}
				else if ($fcode !== '')
				{
					$where_sql[] = '1 = 2';
				}
			}
			else
			{
				$filter['fcode'] = '';
			}
		}
	}

	if (isset($filter['q']) && $filter['q'])
	{
		$where_sql[] = '(m.content ilike ? or m."Description" ilike ?)';
		$params_sql[] = '%' . $filter['q'] . '%';
		$params_sql[] = '%' . $filter['q'] . '%';
	}

	if (isset($filter['cid']) && $filter['cid'])
	{
		$cat_ary = [];

		$st = $app['db']->prepare('select id
			from categories
			where id_parent = ?');
		$st->bindValue(1, $filter['cid']);
		$st->execute();

		while ($row = $st->fetch())
		{
			$cat_ary[] = $row['id'];
		}

		if (count($cat_ary))
		{
			$where_sql[] = 'm.id_category in (' . implode(', ', $cat_ary) . ')';
		}
		else
		{
			$where_sql[] = 'm.id_category = ?';
			$params_sql[] = $filter['cid'];
		}
	}

	if (isset($filter['valid']) && count($filter['valid']) !== 2)
	{
		if (isset($filter['valid']['yes']))
		{
			$where_sql[] = 'm.validity >= now()';
		}
		else if (isset($filter['valid']['no']))
		{
			$where_sql[] = 'm.validity < now()';
		}
	}

	if (isset($filter['type']) && count($filter['type']) !== 2)
	{
		if (isset($filter['type']['want']))
		{
			$where_sql[] = 'm.msg_type = 0';
		}
		else if (isset($filter['type']['offer']))
		{
			$where_sql[] = 'm.msg_type = 1';
		}
	}

	if (isset($filter['ustatus']) && count($filter['ustatus']) === 3)
	{
		$where_sql[] = 'u.status in (1, 2)';
	}
	else
	{
		if (isset($filter['ustatus']['new']))
		{
			$ustatus_sql[] = '(u.adate > ? and u.status = 1)';
			$params_sql[] = gmdate('Y-m-d H:i:s', $newusertreshold);
		}

		if (isset($filter['ustatus']['leaving']))
		{
			$ustatus_sql[] = 'u.status = 2';
		}

		if (isset($filter['ustatus']['active']))
		{
			$ustatus_sql[] = '(u.adate <= ? and u.status = 1)';
			$params_sql[] = gmdate('Y-m-d H:i:s', $newusertreshold);
		}

		if (count($ustatus_sql))
		{
			$where_sql[] = '(' . implode(' or ', $ustatus_sql) . ')';
		}
	}
}
else
{
	if ($type !== 'wants')
	{
		$filter['type']['offer'] = 'on';
	}
	if ($type !== 'offers')
	{
		$filter['type']['want'] = 'on';
	}
	$filter['ustatus'] = [
		'active' 	=> 'on',
		'new'		=> 'on',
		'leaving'	=> 'on',
	];
	$filter['valid'] = [
		'yes'	=> 'on',
		'no'	=> 'on',
	];
}

$params['f'] = $filter;

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

$row_count = $app['db']->fetchColumn('select count(m.*)
	from messages m, users u
	where m.id_user = u.id' . $where_sql, $params_sql);

$query .= $asc ? 'asc ' : 'desc ';
$query .= ' limit ' . $limit . ' offset ' . $start;

$messages = $app['db']->fetchAll($query, $params_sql);

if ($v_extended)
{
	$ids = $imgs = [];

	foreach ($messages as $msg)
	{
		$ids[] = $msg['id'];
	}

	$_imgs = $app['db']->executeQuery('select mp.msgid, mp."PictureFile"
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

$app['pagination']->init('messages', $row_count, $params, $inline);

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

if (!($filter['cid'] ?? false))
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

if (!$s_guest && $count_interlets_groups)
{
	$tableheader_ary += [
		'm.local' => array_merge($asc_preset_ary, [
			'lbl' 	=> 'Zichtbaarheid',
			'data_hide'	=> 'phone, tablet',
		]),
	];
}

$tableheader_ary[$orderby]['asc'] = $asc ? 0 : 1;
$tableheader_ary[$orderby]['indicator'] = $asc ? '-asc' : '-desc';

unset($tableheader_ary['m.cdate']);

$cats = ['' => '-- alle categorieën --'];

$categories = $cat_params  = [];

if ($uid)
{
	$st = $app['db']->executeQuery('select c.*
		from categories c, messages m
		where m.id_category = c.id
			and m.id_user = ?
		order by c.fullname', [$uid]);
}
else
{
	$st = $app['db']->executeQuery('select * from categories order by fullname');
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
}

$filter_panel_open = (($filter['fcode'] ?? false) && !$uid)
	|| count($filter['type']) !== 2
	|| count($filter['valid']) !== 2
	|| count($filter['ustatus']) !== 3;

$filtered = ($filter['q'] ?? false) || $filter_panel_open;

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

$h1 .= isset($filter['cid']) && $filter['cid'] ? ', categorie "' . $categories[$filter['cid']] . '"' : '';
$h1 .= $filtered ? ' <small>Gefilterd</small>' : '';

$fa = 'newspaper-o';

if (!$inline)
{
	$v_params = $params;

	$top_buttons_right = '<span class="btn-group" role="group">';

	$active = $v_list ? ' active' : '';
	$v_params['view'] = 'list';
	$top_buttons_right .= aphp('messages', $v_params, '', 'btn btn-default' . $active, 'lijst', 'align-justify');

	$active = $v_extended ? ' active' : '';
	$v_params['view'] = 'extended';
	$top_buttons_right .= aphp('messages', $v_params, '', 'btn btn-default' . $active, 'Lijst met omschrijvingen', 'th-list');

	$top_buttons_right .= '</span>';

	$app['assets']->add(['msgs.js', 'table_sel.js', 'typeahead', 'typeahead.js']);

	include __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="get" class="form-horizontal">';

	echo '<div class="row">';

	echo '<div class="col-sm-5">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-search"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="q" value="';
	echo $filter['q'] ?? '';
	echo '" name="f[q]" placeholder="Zoeken">';
	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-5 col-xs-10">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-clone"></i>';
	echo '</span>';
	echo '<select class="form-control" id="cid" name="f[cid]">';

	echo get_select_options($cats, $filter['cid'] ?? 0);

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
	echo $filter_panel_open ? '' : ' class="collapse"';
	echo '>';

	echo '<div class="row">';

	$offerwant_options = [
		'want'		=> 'Vraag',
		'offer'		=> 'Aanbod',
	];

	echo '<div class="col-md-3">';
	echo '<div class="input-group margin-bottom">';

	echo get_checkbox_filter($offerwant_options, 'type', $filter);

	echo '</div>';
	echo '</div>';

	$valid_options = [
		'yes'		=> 'Geldig',
		'no'		=> 'Vervallen',
	];

	echo '<div class="col-md-3">';
	echo '<div class="input-group margin-bottom">';

	echo get_checkbox_filter($valid_options, 'valid', $filter);

	echo '</div>';
	echo '</div>';

	$user_status_options = [
		'active'	=> 'Niet in- of uitstappers',
		'new'		=> 'Instappers',
		'leaving'	=> 'Uitstappers',
	];

	echo '<div class="col-md-6">';
	echo '<div class="input-group margin-bottom">';

	echo get_checkbox_filter($user_status_options, 'ustatus', $filter);

	echo '</div>';
	echo '</div>';

	echo '</div>';

	echo '<div class="row">';

	echo '<div class="col-sm-10">';
	echo '<div class="input-group margin-bottom">';
	echo '<span class="input-group-addon" id="fcode_addon">Van ';
	echo '<span class="fa fa-user"></span></span>';

	echo '<input type="text" class="form-control" ';
	echo 'aria-describedby="fcode_addon" ';
	echo 'data-typeahead="' . $app['typeahead']->get('users_active') . '" ';
	echo 'data-newuserdays="' . $app['config']->get('newuserdays') . '" ';
	echo 'name="f[fcode]" id="fcode" placeholder="Account" ';
	echo 'value="';
	echo $filter['fcode'] ?? '';
	echo '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-2">';
	echo '<input type="submit" id="filter_submit" value="Toon" class="btn btn-default btn-block" name="f[s]">';
	echo '</div>';

	echo '</div>';
	echo '</div>';

	$params_form = $params;
	unset($params_form['f'], $params_form['start']);

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

	echo '<h3><i class="fa fa-newspaper-o"></i> ';
	echo $h1;
	echo $recent ? '' : '<span class="inline-buttons">' . $top_buttons . '</span>';
	echo '</h3>';
}

if (!$recent)
{
	echo $app['pagination']->get();
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
		echo $app['pagination']->get();
	}

	if (!$inline)
	{
		include __DIR__ . '/include/footer.php';
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
			echo '<input type="checkbox" name="sel_' . $msg['id'] . '" value="1"';
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
			echo $msg['postcode'] ?? '';
			echo '</td>';
		}

		if (!($filter['cid'] ?? false))
		{
			echo '<td>';
			echo aphp('messages', $cat_params[$msg['id_category']], $categories[$msg['id_category']]);
			echo '</td>';
		}

		echo '<td>';
		echo $app['date_format']->get($msg['validity'], 'day');
		echo '</td>';

		if (!$s_guest && $count_interlets_groups)
		{
			echo '<td>' . $app['access_control']->get_label($msg['local'] ? 'users' : 'interlets') . '</td>';
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
			echo '<img class="media-object" src="' . $app['s3_img_url'] . $imgs[$msg['id']] . '" width="150">';
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
	echo $app['pagination']->get();
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
		echo '<span class="btn btn-default" id="invert_selection">Selectie omkeren</span>&nbsp;';
		echo '<span class="btn btn-default" id="select_all">Selecteer alle</span>&nbsp;';
		echo '<span class="btn btn-default" id="deselect_all">De-selecteer alle</span>';
		echo '</div></div>';

		echo '<h3>Bulk acties met geselecteerd vraag en aanbod</h3>';

		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';

		echo '<ul class="nav nav-tabs" role="tablist">';
		echo '<li class="active"><a href="#extend_tab" data-toggle="tab">Verlengen</a></li>';

		if ($app['config']->get('template_lets') && $app['config']->get('interlets_en'))
		{
			echo '<li>';
			echo '<a href="#access_tab" data-toggle="tab">';
			echo 'Zichtbaarheid</a><li>';
		}

		echo '</ul>';

		echo '<div class="tab-content">';

		echo '<div role="tabpanel" class="tab-pane active" id="extend_tab">';
		echo '<h3>Vraag en aanbod verlengen</h3>';

		echo '<form method="post" class="form-horizontal">';

		echo '<div class="form-group">';
		echo '<label for="extend" class="col-sm-2 control-label">Verlengen met</label>';
		echo '<div class="col-sm-10">';
		echo '<select name="extend" id="extend" class="form-control">';
		echo get_select_options($extend_options, '30');
		echo "</select>";
		echo '</div>';
		echo '</div>';

		echo '<input type="submit" value="Verlengen" name="extend_submit" class="btn btn-primary">';

		echo $app['form_token']->get_hidden_input();

		echo '</form>';

		echo '</div>';

		if ($app['config']->get('template_lets') && $app['config']->get('interlets_en'))
		{
			echo '<div role="tabpanel" class="tab-pane" id="access_tab">';
			echo '<h3>Zichtbaarheid instellen</h3>';

			echo '<form method="post" class="form-horizontal">';

			echo $app['access_control']->get_radio_buttons(false, false, 'admin');

			echo '<input type="submit" value="Aanpassen" name="access_submit" class="btn btn-primary">';

			echo $app['form_token']->get_hidden_input();

			echo '</form>';

			echo '</div>';
		}

		echo '</div>';

		echo '<div class="clearfix"></div>';
		echo '</div>';

		echo '</div></div>';
	}

	include __DIR__ . '/include/footer.php';
}
else if ($v_extended)
{
	include __DIR__ . '/include/footer.php';
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

function get_checkbox_filter(array $checkbox_ary, string $filter_id, array $filter_ary):string
{
	$out = '';

	foreach ($checkbox_ary as $key => $label)
	{
		$out .= '<span class="input-group-addon">';
		$out .= '<label class="col-xs-12">';
		$out .= '<input type="checkbox" name="f[' . $filter_id . '][' . $key . ']"';
		$out .= isset($filter_ary[$filter_id][$key]) ? ' checked' : '';
		$out .= '>&nbsp;';
		$out .= $label;
		$out .= '</label>';
		$out .= '</span>';
	}

	return $out;
}

function get_radio(array $radio_ary, string $name, string $selected, bool $required):string
{
	$out = '';

	foreach ($radio_ary as $value => $label)
	{
		$out .= '<span class="input-group-addon">';
		$out .= '<label class="col-xs-12">';
		$out .= '<input type="radio" name="' . $name . '" ';
		$out .= 'value="' . $value . '"';
		$out .= (string) $value === $selected ? ' checked' : '';
		$out .= $required ? ' required' : '';
		$out .= '>&nbsp;';
		$out .= $label;
		$out .= '</label>';
		$out .= '</span>';
	}

	return $out;
}
