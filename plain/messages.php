<?php

if ($app['s_anonymous'])
{
	echo 'anonymous --';
//	exit;
}

use render\link;

$id = $_GET['id'] ?? false;
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = isset($_GET['add']);
$uid = $_GET['uid'] ?? false;
$img = isset($_GET['img']);
$insert_img = isset($_GET['insert_img']);
$img_del = $_GET['img_del'] ?? false;
$images = $_FILES['images'] ?? false;
$mail = isset($_POST['mail']);
$selected_msgs = (isset($_POST['sel']) && $_POST['sel'] != '') ? explode(',', $_POST['sel']) : [];
$extend_submit = isset($_POST['extend_submit']);
$extend = $_POST['extend'] ?? false;
$access_submit = isset($_POST['access_submit']);

$filter = $app['request']->query->get('f', []);
$pag = $app['request']->query->get('p', []);
$sort = $app['request']->query->get('s', []);

if ($app['request']->isMethod('POST')
	&& $app['s_guest']
	&& ($add || $edit || $del || $img || $img_del || $images
		|| $extend_submit || $access_submit || $extend))
{
	$app['alert']->error('Geen toegang als gast tot deze actie');
	$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
}

if ($app['request']->isMethod('GET'))
{
	$extend = $_GET['extend'] ?? false;
}

/*
 * bulk actions (set access or validity)
 */
if ($app['request']->isMethod('POST')
	&& (($extend_submit && $extend)
		|| ($access_submit))
	& ($app['s_admin'] || $app['s_user']))
{
	if (!is_array($selected_msgs) || !count($selected_msgs))
	{
		$app['alert']->error('Selecteer ten minste één vraag
			of aanbod voor deze actie.');
		$app['link']->redirect('messages', $app['pp_ary'], []);
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

	$rows = $app['db']->executeQuery('select id_user, id, content, validity
		from ' . $app['tschema'] . '.messages
		where id in (?)',
		[$selected_msgs],
		[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($rows as $row)
	{
		if (!$app['s_admin']
			&& $app['s_user']
			&& ($row['id_user'] !== $app['s_id']))
		{
			$errors[] = 'Je bent niet de eigenaar van vraag of aanbod ' .
				$row['content'] . ' ( ' . $row['id'] . ')';
			$app['alert']->error($errors);
			$app['link']->redirect('messages', $app['pp_ary'], []);
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

			if (!$app['db']->update($app['tschema'] . '.messages',
				$m,
				['id' => $id]))
			{
				$app['alert']->error('Fout: ' . $row['content'] . ' is niet verlengd.');
				$app['link']->redirect('messages', $app['pp_ary'], []);
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

		$app['link']->redirect('messages', $app['pp_ary'], []);
	}

	if ($access_submit && !count($errors))
	{
		$access = $app['request']->request->get('access', '');

		if (!$access)
		{
			$errors[] = 'Vul een zichtbaarheid in.';
		}

		if (!count($errors))
		{
			$m = [
				'local' => $access !== 'guest',
				'mdate' => gmdate('Y-m-d H:i:s')
			];

			$app['db']->beginTransaction();

			try
			{
				foreach ($validity_ary as $id => $validity)
				{
					$app['db']->update($app['tschema'] . '.messages', $m, ['id' => $id]);
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

				$app['link']->redirect('messages', $app['pp_ary'], []);
			}
			catch(Exception $e)
			{
				$app['db']->rollback();
				throw $e;
				$app['alert']->error('Fout bij het opslaan.');
				$app['link']->redirect('messages', $app['pp_ary'], []);
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
		from ' . $app['tschema'] . '.messages m, ' .
			$app['tschema'] . '.categories c
		where m.id = ?
			and c.id = m.id_category', [$id]);

	$message['access'] = $message['local'] ? 'user' : 'guest';

	if (!$message)
	{
		$app['alert']->error('Bericht niet gevonden.');
		$app['link']->redirect('messages', $app['pp_ary'], []);
	}

	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $app['s_id'] == $message['id_user']
		&& $message['id_user'];

	if ($message['access'] === 'user' && $app['s_guest'])
	{
		$app['alert']->error('Je hebt geen toegang tot dit bericht.');
		$app['link']->redirect('messages', $app['pp_ary'], []);
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
	if (!($s_owner || $app['s_admin']))
	{
		$app['alert']->error('Je hebt onvoldoende rechten om ' .
			$ow_type_this . ' te verlengen.');

		$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
	}

	$validity = gmdate('Y-m-d H:i:s', strtotime($message['validity']) + (86400 * (int) $extend));

	$m = [
		'validity'		=> $validity,
		'mdate'			=> gmdate('Y-m-d H:i:s'),
		'exp_user_warn'	=> 'f',
	];

	if (!$app['db']->update($app['tschema'] . '.messages', $m, ['id' => $id]))
	{
		$app['alert']->error('Fout: ' . $ow_type_the . ' is niet verlengd.');
		$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
	}

	$app['alert']->success($ow_type_uc_the . ' is verlengd.');
	$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
}

/**
 * post images
 */
if ($app['request']->isMethod('POST')
	&& $img && $images && !$app['s_guest'])
{
	$ret_ary = [];

	if ($id)
	{
		if (!$s_owner && !$app['s_admin'])
		{
			$ret_ary[] = ['error' => 'Je hebt onvoldoende rechten
				om een afbeelding op te laden voor
				dit vraag of aanbod bericht.'];
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

		if ($size > (400 * 1024))
		{
			$ret_ary[] = [
				'name'	=> $name,
				'size'	=> $size,
				'error' => 'Te groot bestand',
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
			$id = $app['db']->fetchColumn('select max(id)
				from ' . $app['tschema'] . '.messages');
			$id++;
		}

		$filename = $app['tschema'] . '_m_' . $id . '_';
		$filename .= sha1($filename . microtime()) . '.jpg';

		$err = $app['s3']->img_upload($filename, $tmpfile2);

		if ($err)
		{
			$app['monolog']->error('Upload fail : ' . $err,
				['schema' => $app['tschema']]);

			$ret_ary = [['error' => 'Opladen mislukt.']];
			break;
		}
		else
		{
			if ($insert_img)
			{
				$app['db']->insert($app['tschema'] . '.msgpictures', [
					'msgid'			=> $id,
					'"PictureFile"'	=> $filename]);

				$app['monolog']->info('Message-Picture ' .
					$filename . ' uploaded and inserted in db.',
					['schema' => $app['tschema']]);
			}
			else
			{
				$app['monolog']->info('Message-Picture ' .
					$filename . ' uploaded, not (yet) inserted in db.',
					['schema' => $app['tschema']]);
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

if ($img_del == 'all' && $id
	&& $app['request']->isMethod('POST'))
{
	if (!($s_owner || $app['s_admin']))
	{
		$app['alert']->error('Je hebt onvoldoende rechten
			om afbeeldingen te verwijderen voor ' . $ow_type_this);
	}

	$app['db']->delete($app['tschema'] . '.msgpictures', ['msgid' => $id]);

	$app['alert']->success('De afbeeldingen voor ' . $ow_type_this .
		' zijn verwijderd.');

	$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
}

/*
 * delete an image
 */
if ($img_del && $app['request']->isMethod('POST')
	&& ctype_digit((string) $img_del))
{
	if (!($msg = $app['db']->fetchAssoc('select m.id_user, p."PictureFile"
		from ' . $app['tschema'] . '.msgpictures p, ' . $app['tschema'] . '.messages m
		where p.msgid = m.id
			and p.id = ?', [$img_del])))
	{
		echo json_encode(['error' => 'Afbeelding niet gevonden.']);
		exit;
	}

	$s_owner = !$app['s_guest']
		&& $app['s_system_self']
		&& $msg['id_user'] == $app['s_id']
		&& $msg['id_user'];

	if (!($s_owner || $app['s_admin']))
	{
		echo json_encode(['error' => 'Onvoldoende rechten om deze afbeelding te verwijderen.']);
		exit;
	}

	$app['db']->delete($app['tschema'] . '.msgpictures', ['id' => $img_del]);

	echo json_encode(['success' => true]);
	exit;
}

/**
 * delete images form
 */

if ($img_del == 'all' && $id)
{
	if (!($app['s_admin'] || $s_owner))
	{
		$app['alert']->error('Je kan geen afbeeldingen verwijderen voor ' . $ow_type_this);
		$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
	}

	$images = [];

	$st = $app['db']->prepare('select id, "PictureFile"
		from ' . $app['tschema'] . '.msgpictures
		where msgid = ?');
	$st->bindValue(1, $id);
	$st->execute();

	while ($row = $st->fetch())
	{
		$images[$row['id']] = $row['PictureFile'];
	}

	if (!count($images))
	{
		$app['alert']->error($ow_type_uc_the . ' heeft geen afbeeldingen.');
		$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
	}

	$str_this_ow = $ow_type;
	$str_this_ow .= ' "';
	$str_this_ow .= $app['link']->link_no_attr('messages', $app['pp_ary'],
		['id' => $id], $message['content']);
	$str_this_ow .= '"';

	$app['heading']->add('Afbeeldingen verwijderen voor ' . $str_this_ow);
	$app['heading']->fa('newspaper-o');

	$app['assets']->add(['msg_img_del.js']);

	include __DIR__ . '/../include/header.php';

	if ($app['s_admin'])
	{
		echo 'Gebruiker: ';
		echo $app['account']->link($message['id_user'], $app['pp_ary']);
	}

	echo '<div class="row">';

	foreach ($images as $img_id => $file)
	{
		echo '<div class="col-xs-6 col-md-3">';
		echo '<div class="thumbnail">';
		echo '<img src="';
		echo $app['s3_url'] . $file;
		echo '" class="img-rounded">';

		echo '<div class="caption">';
		echo '<span class="btn btn-danger" data-img-del="';
		echo $img_id;
		echo '" ';
		echo 'data-url="';

		echo $app['link']->context_path('messages', $app['pp_ary'],
			['img_del' => $img_id]);

		echo '" role="button">';
        echo '<i class="fa fa-times"></i> ';
        echo 'Verwijderen</span>';
		echo '</div>';
 		echo '</div>';
		echo '</div>';
	}

	echo '</div>';

	echo '<form method="post">';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<h3>Alle afbeeldingen verwijderen voor ';
	echo $str_this_ow;
	echo '?</h3>';

	echo $app['link']->btn_cancel('messages', $app['pp_ary'], ['id' => $id]);

	echo '&nbsp;';
	echo '<input type="submit" value="Alle verwijderen" name="zend" class="btn btn-danger">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/../include/footer.php';

	exit;
}

/*
 * send email
 */
if ($mail && $app['request']->isMethod('POST') && $id)
{
	$content = $_POST['content'];
	$cc = isset($_POST['cc']);

	$to_user = $app['user_cache']->get($message['id_user'], $app['tschema']);

	if (!$app['s_admin'] && !in_array($to_user['status'], [1, 2]))
	{
		$app['alert']->error('Je hebt geen rechten om een
			bericht naar een niet-actieve gebruiker te sturen');
		$app['link']->redirect('messages', $app['pp_ary'], []);
	}

	if ($app['s_master'])
	{
		$app['alert']->error('Het master account
			kan geen berichten versturen.');
		$app['link']->redirect('messages', $app['pp_ary'], []);
	}

	if (!$app['s_schema'])
	{
		$app['alert']->error('Je hebt onvoldoende rechten
			om een E-mail bericht te versturen.');
		$app['link']->redirect('messages', $app['pp_ary'], []);
	}

	if (!$content)
	{
		$app['alert']->error('Fout: leeg bericht. E-mail niet verzonden.');
		$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
	}

	$reply_ary = $app['mail_addr_user']->get($app['s_id'], $app['s_schema']);

	if (!count($reply_ary))
	{
		$app['alert']->error('Fout: Je kan geen berichten naar een andere gebruiker
			verzenden als er geen E-mail adres is ingesteld voor je eigen account.');

		$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
	}

	$from_contacts = $app['db']->fetchAll('select c.value, tc.abbrev
		from ' . $app['s_schema'] . '.contact c, ' .
			$app['s_schema'] . '.type_contact tc
		where c.flag_public >= ?
			and c.id_user = ?
			and c.id_type_contact = tc.id',
			[\util\cnst::ACCESS_ARY[$to_user['accountrole']], $app['s_id']]);

	$message['type'] = $message['msg_type'] ? 'offer' : 'want';
	$message['offer'] = $message['type'] === 'offer';
	$message['want'] = $message['type'] === 'want';

	$from_user = $app['user_cache']->get($app['s_id'], $app['s_schema']);

	$vars = [
		'from_contacts'		=> $from_contacts,
		'from_user'			=> $from_user,
		'from_schema'		=> $app['s_schema'],
		'is_same_system'	=> $app['s_system_self'],
		'to_user'			=> $to_user,
		'to_schema'			=> $app['tschema'],
		'msg_content'		=> $content,
		'message'			=> $message,
	];

	$mail_template = $app['s_system_self']
		? 'message_msg/msg'
		: 'message_msg/msg_intersystem';

	$app['queue.mail']->queue([
		'schema'	=> $app['tschema'],
		'to'		=> $app['mail_addr_user']->get($to_user['id'], $app['tschema']),
		'reply_to'	=> $reply_ary,
		'template'	=> $mail_template,
		'vars'		=> $vars,
	], 8500);

	if ($cc)
	{
		$mail_template = $app['s_system_self']
			? 'message_msg/copy'
			: 'message_msg/copy_intersystem';

		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'to'		=> $app['mail_addr_user']->get($app['s_id'], $app['s_schema']),
			'template'	=> $mail_template,
			'vars'		=> $vars,
		], 8000);
	}

	$app['alert']->success('Mail verzonden.');

	$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
}

/*
 * delete message
 */
if ($del)
{
	if (!($s_owner || $app['s_admin']))
	{
		$app['alert']->error('Je hebt onvoldoende rechten om ' . $ow_type_this . ' te verwijderen.');
		$app['link']->redirect('messages', $app['pp_ary'], ['id' => $del]);
	}

	if($app['request']->isMethod('POST'))
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
		}

		$app['db']->delete($app['tschema'] . '.msgpictures', ['msgid' => $del]);

		if ($app['db']->delete($app['tschema'] . '.messages', ['id' => $del]))
		{
			$column = 'stat_msgs_';
			$column .= $message['msg_type'] ? 'offers' : 'wanted';

			$app['db']->executeUpdate('update ' . $app['tschema'] . '.categories
				set ' . $column . ' = ' . $column . ' - 1
				where id = ?', [$message['id_category']]);

			$app['alert']->success(ucfirst($ow_type_this) . ' is verwijderd.');
			$app['link']->redirect('messages', $app['pp_ary'], []);
		}

		$app['alert']->error(ucfirst($ow_type_this) . ' is niet verwijderd.');
	}

	$app['heading']->add(ucfirst($ow_type_this) . ' ');

	$app['heading']->add($app['link']->link_no_attr('messages', $app['pp_ary'],
		['id' => $del], $message['content']));

	$app['heading']->add(' verwijderen?');
	$app['heading']->fa('newspaper-o');

	include __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info printview">';
	echo '<div class="panel-heading">';

	echo '<dl>';

	echo '<dt>Wie</dt>';
	echo '<dd>';
	echo $app['account']->link($message['id_user'], $app['pp_ary']);
	echo '</dd>';

	echo '<dt>Categorie</dt>';
	echo '<dd>';
	echo htmlspecialchars($message['catname'], ENT_QUOTES);
	echo '</dd>';

	echo '<dt>Geldig tot</dt>';
	echo '<dd>';
	echo $message['validity'];
	echo '</dd>';

	if ($app['intersystem_en'] && $app['intersystems']->get_count($app['tschema']))
	{
		echo '<dt>Zichtbaarheid</dt>';
		echo '<dd>';
		echo $app['lbl_access']->get_label($message['local'] ? 'user' : 'guest');
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

	echo $app['link']->btn_cancel('messages', $app['pp_ary'], ['id' => $id]);

	echo '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	echo $app['form_token']->get_hidden_input();
	echo '</form></p>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/../include/footer.php';
	exit;
}

/*
 * edit - add
 */
if (($edit || $add))
{
	if (!($app['s_admin'] || $app['s_user']) && $add)
	{
		$app['alert']->error('Je hebt onvoldoende rechten om
			een vraag of aanbod toe te voegen.');
		$app['link']->redirect('messages', $app['pp_ary'], []);
	}

	if (!($app['s_admin'] || $s_owner) && $edit)
	{
		$app['alert']->error('Je hebt onvoldoende rechten om ' .
			$ow_type_this . ' aan te passen.');
		$app['link']->redirect('messages', $app['pp_ary'], ['id' => $edit]);
	}

	if ($app['request']->isMethod('POST'))
	{
		$validity = $_POST['validity'];

		if (!ctype_digit((string) $validity))
		{
			$errors[] = 'De geldigheid in dagen moet een positief getal zijn.';
		}

		$vtime = time() + ((int) $validity * 86400);
		$vtime =  gmdate('Y-m-d H:i:s', $vtime);

		if ($app['s_admin'])
		{
			[$user_letscode] = explode(' ', trim($_POST['user_letscode']));
			$user_letscode = trim($user_letscode);
			$user = $app['db']->fetchAssoc('select *
				from ' . $app['tschema'] . '.users
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
			'id_user'		=> $app['s_admin'] ? (int) $user['id'] : ($app['s_master'] ? 0 : $app['s_id']),
			'id_category'	=> $_POST['id_category'],
			'amount'		=> $_POST['amount'],
			'units'			=> $_POST['units'],
		];

		$deleted_images = isset($_POST['deleted_images']) && $edit ? $_POST['deleted_images'] : [];
		$uploaded_images = $_POST['uploaded_images'] ?? [];

		if ($app['count_intersystems'])
		{
			$access = $app['request']->query->get('access', '');

			if (!$access)
			{
				$errors[] = 'Vul een zichtbaarheid in.';
			}

			$msg['local'] = $access === 'guest' ? 0 : 1;
		}
		else if ($add)
		{
			$msg['local'] = 1;
		}

		if (!ctype_digit((string) $msg['amount']) && $msg['amount'] != '')
		{
			$err = 'De (richt)prijs in ';
			$err .= $app['config']->get('currency', $app['tschema']);
			$err .= ' moet nul of een positief getal zijn.';
			$errors[] = $err;
		}

		if (!$msg['id_category'])
		{
			$errors[] = 'Geieve een categorie te selecteren.';
		}
		else if(!$app['db']->fetchColumn('select id
			from ' . $app['tschema'] . '.categories
			where id = ?', [$msg['id_category']]))
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

		if(!($app['db']->fetchColumn('select id
			from ' . $app['tschema'] . '.users
			where id = ? and status <> 0', [$msg['id_user']])))
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

			if ($app['db']->insert($app['tschema'] . '.messages', $msg))
			{
				$id = $app['db']->lastInsertId($app['tschema'] . '.messages_id_seq');

				$stat_column = 'stat_msgs_';
				$stat_column .= $msg['msg_type'] ? 'offers' : 'wanted';

				$app['db']->executeUpdate('update ' . $app['tschema'] . '.categories
					set ' . $stat_column . ' = ' . $stat_column . ' + 1
					where id = ?', [$msg['id_category']]);

				if (count($uploaded_images))
				{
					foreach ($uploaded_images as $img)
					{
						$img_errors = [];

						[$sch, $img_type, $msgid, $hash] = explode('_', $img);

						if ($sch != $app['tschema'])
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
							if ($app['db']->insert($app['tschema'] . '.msgpictures', [
								'"PictureFile"' => $img,
								'msgid'			=> $id,
							]))
							{
								$app['monolog']->info('message-picture ' . $img .
									' inserted in db.', ['schema' => $app['tschema']]);
							}
							else
							{
								$app['monolog']->error('error message-picture ' . $img .
									' not inserted in db.', ['schema' => $app['tschema']]);
							}

							continue;
						}

						$new_filename = $app['tschema'] . '_m_' . $id . '_';
						$new_filename .= sha1($new_filename . microtime()) . '.jpg';

						$err = $app['s3']->copy($img, $new_filename);

						if (isset($err))
						{
							$app['monolog']->error('message-picture renaming and storing in db ' . $img .
								' not succeeded. ' . $err, ['schema' => $app['tschema']]);
						}
						else
						{
							$app['monolog']->info('renamed ' . $img . ' to ' .
								$new_filename, ['schema' => $app['tschema']]);

							if ($app['db']->insert($app['tschema'] . '.msgpictures', [
								'"PictureFile"'		=> $new_filename,
								'msgid'				=> $id,
							]))
							{
								$app['monolog']->info('message-picture ' . $new_filename .
									' inserted in db.', ['schema' => $app['tschema']]);
							}
							else
							{
								$app['monolog']->error('error: message-picture ' . $new_filename .
									' not inserted in db.', ['schema' => $app['tschema']]);
							}
						}
					}
				}

				$app['alert']->success('Nieuw vraag of aanbod toegevoegd.');
				$app['link']->redirect('messages', $app['pp_ary'], ['id' => $id]);
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
				$app['db']->update($app['tschema'] . '.messages', $msg, ['id' => $edit]);

				if ($msg['msg_type'] != $message['msg_type'] || $msg['id_category'] != $message['id_category'])
				{
					$column = 'stat_msgs_';
					$column .= ($message['msg_type']) ? 'offers' : 'wanted';

					$app['db']->executeUpdate('update ' . $app['tschema'] . '.categories
						set ' . $column . ' = ' . $column . ' - 1
						where id = ?', [$message['id_category']]);

					$column = 'stat_msgs_';
					$column .= ($msg['msg_type']) ? 'offers' : 'wanted';

					$app['db']->executeUpdate('update ' . $app['tschema'] . '.categories
						set ' . $column . ' = ' . $column . ' + 1
						where id = ?', [$msg['id_category']]);
				}

				if (count($deleted_images))
				{
					foreach ($deleted_images as $img)
					{
						if ($app['db']->delete($app['tschema'] . '.msgpictures', [
							'msgid'		=> $edit,
							'"PictureFile"'	=> $img,
						]))
						{
							$app['monolog']->info('message-picture ' . $img .
								' deleted from db.', ['schema' => $app['tschema']]);
						}
					}
				}

				if (count($uploaded_images))
				{
					foreach ($uploaded_images as $img)
					{
						$img_errors = [];

						[$sch, $img_type, $msgid, $hash] = explode('_', $img);

						if ($sch != $app['tschema'])
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

						if ($app['db']->insert($app['tschema'] . '.msgpictures', [
							'"PictureFile"' => $img,
							'msgid'			=> $edit,
						]))
						{
							$app['monolog']->info('message-picture ' . $img .
								' inserted in db.', ['schema' => $app['tschema']]);
						}
						else
						{
							$app['monolog']->error('error message-picture ' . $img .
								' not inserted in db.', ['schema' => $app['tschema']]);
						}
					}
				}

				$app['db']->commit();
				$app['alert']->success('Vraag/aanbod aangepast');
				$app['link']->redirect('messages', $app['pp_ary'], ['id' => $edit]);
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
			$app['link']->redirect('messages', $app['pp_ary'], []);
		}

		$msg['description'] = $msg['"Description"'];

		$images = $edit ? $app['db']->fetchAll('select *
			from ' . $app['tschema'] . '.msgpictures
			where msgid = ?', [$edit]) : [];

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
			from ' . $app['tschema'] . '.messages m
			where m.id = ?', [$edit]);

		$msg['description'] = $msg['Description'];
		unset($msg['Description']);

		$rev = round((strtotime($msg['validity']) - time()) / (86400));
		$msg['validity'] = $rev < 1 ? 0 : $rev;

		$user = $app['user_cache']->get($msg['id_user'], $app['tschema']);

		$user_letscode = $user['letscode'] . ' ' . $user['name'];

		$images = $app['db']->fetchAll('select *
			from ' . $app['tschema'] . '.msgpictures
			where msgid = ?', [$edit]);
	}
	else if ($add)
	{
		$msg = [
			'validity'		=> $app['config']->get('msgs_days_default', $app['tschema']),
			'content'		=> '',
			'description'	=> '',
			'msg_type'		=> 'none',
			'id_user'		=> $app['s_master'] ? 0 : $app['s_id'],
			'id_category'	=> '',
			'amount'		=> '',
			'units'			=> '',
			'local'			=> 0,
		];

	// checkthis
		$uid = (isset($_GET['uid'])
			&& $app['s_admin']) ? $_GET['uid'] :
				(($app['s_master']) ? 0 : $app['s_id']);

		if ($app['s_master'])
		{
			$user_letscode = '';
		}
		else
		{
			$user = $app['user_cache']->get($uid, $app['tschema']);

			$user_letscode = $user['letscode'] . ' ' . $user['name'];
		}

		$images = [];
	}

	$cat_list = ['' => ''];

	$rs = $app['db']->prepare('select id, fullname
		from ' . $app['tschema'] . '.categories
		where leafnote=1
		order by fullname');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$cat_list[$row['id']] = $row['fullname'];
	}

	array_walk($msg, function(&$value, $key){ $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); });

	if ($app['s_admin'])
	{
		$app['assets']->add([
			'typeahead',
			'typeahead.js',
		]);
	}

	$app['assets']->add([
		'fileupload',
		'msg_edit.js',
		'access_input_cache.js',
	]);

	$app['heading']->add($add ? 'Nieuw Vraag of Aanbod toevoegen' : 'Vraag of Aanbod aanpassen');
	$app['heading']->fa('newspaper-o');

	include __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	if($app['s_admin'])
	{
		echo '<div class="form-group">';
		echo '<label for="user_letscode" class="control-label">';
		echo '<span class="label label-info">Admin</span> ';
		echo 'Gebruiker</label>';
		echo '<div class="input-group">';
		echo '<span class="input-group-addon">';
		echo '<i class="fa fa-user"></i>';
		echo '</span>';
		echo '<input type="text" class="form-control" ';
		echo 'id="user_letscode" name="user_letscode" ';
		echo 'data-typeahead="';
		echo $app['typeahead']->get($app['pp_ary'], [['accounts', [
			'status'	=> 'active',
		]]]);
		echo '" ';
		echo 'data-newuserdays="';
		echo $app['config']->get('newuserdays', $app['tschema']);
		echo '" ';
		echo 'value="';
		echo $user_letscode;
		echo '" required>';
		echo '</div>';
		echo '</div>';
	}

	echo '<div class="form-group">';
	echo get_radio(['1' => 'Aanbod', '0' => 'Vraag'], 'msg_type', $msg['msg_type'], true);
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="content" class="control-label">';
	echo 'Titel</label>';
	echo '<input type="text" class="form-control" ';
	echo 'id="content" name="content" ';
	echo 'value="';
	echo $msg['content'];
	echo '" maxlength="200" required>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="description" class="control-label">';
	echo 'Omschrijving</label>';
	echo '<textarea name="description" class="form-control" id="description" rows="4" maxlength="2000">';
	echo $msg['description'];
	echo '</textarea>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="id_category" class="control-label">';
	echo 'Categorie</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-clone"></i>';
	echo '</span>';
	echo '<select name="id_category" id="id_category" class="form-control" required>';
	echo $app['select']->get_options($cat_list, $msg['id_category']);
	echo "</select>";
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="validity" class="control-label">';
	echo 'Geldigheid</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo 'dagen';
	echo '</span>';
	echo '<input type="number" class="form-control" ';
	echo 'id="validity" name="validity" min="1" ';
	echo 'value="';
	echo $msg['validity'];
	echo '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="amount" class="control-label">';
	echo 'Aantal';
	echo '</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo $app['config']->get('currency', $app['tschema']);
	echo '</span>';
	echo '<input type="number" class="form-control" ';
	echo 'id="amount" name="amount" min="0" ';
	echo 'value="';
	echo $msg['amount'];
	echo '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="units" class="control-label">';
	echo 'Per (uur, stuk, ...)</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-hourglass-half"></span>';
	echo '</span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="units" name="units" ';
	echo 'value="';
	echo $msg['units'];
	echo '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="fileupload" class="control-label">';
	echo 'Afbeeldingen</label>';
	echo '<div class="row">';

	echo '<div class="col-sm-3 col-md-2 thumbnail-col hidden" ';
	echo 'id="thumbnail_model" ';
	echo 'data-s3-url="';
	echo $app['s3_url'];
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
		echo $app['s3_url'] . $img['PictureFile'];
		echo '" alt="afbeelding">';
		echo '<div class="caption">';

		echo '<p><span class="btn btn-danger img-delete" role="button">';
		echo '<i class="fa fa-times"></i></span></p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	echo '</div>';

	$upload_img_param['form_token'] = $app['form_token']->get();

	$upload_img_param = [
		'img'			=> 1,
		'form_token' 	=> $upload_img_param['form_token'],
	];

	if ($edit)
	{
		$upload_img_param['id'] = $id;
	}

	echo '<span class="btn btn-default fileinput-button">';
	echo '<i class="fa fa-plus" id="img_plus"></i> Opladen';
	echo '<input id="fileupload" type="file" name="images[]" ';
	echo 'data-url="';

	echo $app['link']->context_path('messages', $app['pp_ary'],
		$upload_img_param);

	echo '" ';
	echo 'data-data-type="json" data-auto-upload="true" ';
	echo 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
	echo 'data-max-file-size="999000" ';
	echo 'multiple></span>&nbsp;';

	echo '<p>Afbeeldingen moeten in het jpg/jpeg formaat zijn. ';
	echo 'Je kan ook afbeeldingen hierheen ';
	echo 'verslepen.</p>';
	echo '</div>';

	if ($app['count_intersystems'])
	{
		$access_value = $edit ? ($msg['local'] ? 'users' : 'interlets') : '';

		echo $app['access_control']->get_radio_buttons('messages', $access_value, 'admin');
	}

	$btn = $edit ? 'primary' : 'success';

	echo $app['link']->btn_cancel('messages', $app['pp_ary'], ['id' => $id]);
	echo '&nbsp;';
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

	include __DIR__ . '/../include/footer.php';
	exit;
}

/**
 * show a message
 */
if ($id)
{
	$cc = $app['request']->isMethod('POST') ? $cc : 1;

	$user = $app['user_cache']->get($message['id_user'], $app['tschema']);

	$to = $app['db']->fetchColumn('select c.value
		from ' . $app['tschema'] . '.contact c, ' .
			$app['tschema'] . '.type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', [$user['id']]);

	$mail_to = $app['mail_addr_user']->get($user['id'], $app['tschema']);

	$mail_from = $app['s_schema']
		&& !$app['s_master']
		&& !$app['s_elas_guest']
			? $app['mail_addr_user']->get($app['s_id'], $app['s_schema'])
			: [];

	$balance = $user['saldo'];

	$images = [];

	$st = $app['db']->prepare('select id, "PictureFile"
		from ' . $app['tschema'] . '.msgpictures
		where msgid = ?');
	$st->bindValue(1, $id);
	$st->execute();

	while ($row = $st->fetch())
	{
		$images[$row['id']] = $row['PictureFile'];
	}

	$and_local = ($app['s_guest']) ? ' and local = \'f\' ' : '';

	$prev = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.messages
		where id > ?
		' . $and_local . '
		order by id asc
		limit 1', [$id]);

	$next = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.messages
		where id < ?
		' . $and_local . '
		order by id desc
		limit 1', [$id]);

	$title = $message['content'];

	$contacts = $app['db']->fetchAll('select c.*, tc.abbrev
		from ' . $app['tschema'] . '.contact c, ' .
			$app['tschema'] . '.type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and c.flag_public = 1', [$user['id']]);

	$app['assets']->add([
		'leaflet',
		'jssor',
		'msg.js',
	]);

	if ($app['s_admin'] || $s_owner)
	{
		$app['assets']->add([
			'fileupload',
			'msg_img.js',
		]);
	}

	if ($app['s_admin'] || $s_owner)
	{
		$app['btn_top']->edit('messages', $app['pp_ary'],
			['edit' => $id],	$ow_type_uc . ' aanpassen');

		$app['btn_top']->del('messages', $app['pp_ary'],
			['del' => $id], $ow_type_uc . ' verwijderen');
	}

	if ($message['msg_type'] == 1
		&& ($app['s_admin']
			|| (!$s_owner
				&& $user['status'] != 7
				&& !($app['s_guest']
				&& $app['s_system_self']))))
	{
			$tus = ['add' => 1, 'mid' => $id];

			if (!$app['s_system_self'])
			{
				$tus['tus'] = $app['tschema'];
			}

			$app['btn_top']->add_trans('transactions', $app['s_ary'],
				$tus, 'Transactie voor dit aanbod');
	}

	$prev_ary = $prev ? ['id' => $prev] : [];
	$next_ary = $next ? ['id' => $next] : [];

	$app['btn_nav']->nav('messages', $app['pp_ary'],
		$prev_ary, $next_ary, false);

	$app['btn_nav']->nav_list('messages', $app['pp_ary'],
		[], 'Lijst', 'newspaper-o');

	$app['heading']->add($ow_type_uc);
	$app['heading']->add(': ' . htmlspecialchars($message['content'], ENT_QUOTES));
	$app['heading']->add(strtotime($message['validity']) < time() ? ' <small><span class="text-danger">Vervallen</span></small>' : '');
	$app['heading']->fa('newspaper-o');

	include __DIR__ . '/../include/header.php';

	if ($message['cid'])
	{
		echo '<p>Categorie: ';

		echo $app['link']->link_no_attr('messages', $app['pp_ary'],
			['f' => ['cid' => $message['cid']]], $message['catname']);

		echo '</p>';
	}

	echo '<div class="row">';

	echo '<div class="col-md-6">';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-body">';

	echo '<div id="no_images" ';
	echo 'class="text-center center-body" style="display: none;">';
	echo '<i class="fa fa-image fa-5x"></i> ';
	echo '<p>Er zijn geen afbeeldingen voor ';
	echo $ow_type_this . '</p>';
	echo '</div>';

	echo '<div id="images_con" ';
	echo 'data-bucket-url="' . $app['s3_url'] . '" ';
	echo 'data-images="' . implode(',', $images) . '">';
	echo '</div>';

	echo '</div>';

	if ($app['s_admin'] || $s_owner)
	{
		echo '<div class="panel-footer">';
		echo '<span class="btn btn-success fileinput-button">';
		echo '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
		echo '<input id="fileupload" type="file" name="images[]" ';
		echo 'data-url="';

		echo $app['link']->context_path('messages', $app['pp_ary'], [
			'img' => 1,
			'id' => $id,
			'insert_img' => 1,
		]);

		echo '" ';
		echo 'data-data-type="json" data-auto-upload="true" ';
		echo 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
		echo 'data-max-file-size="999000" ';
		echo 'multiple></span>&nbsp;';

		echo $app['link']->link_fa('messages', $app['pp_ary'],
			[
				'img_del'	=> 'all',
				'id'		=> $id,
			],
			'Afbeeldingen verwijderen',
			[
				'class'	=> 'btn btn-danger',
				'id'	=> 'btn_remove',
				'style'	=> 'display:none;',
			],
			'times'
		);

		echo '<p class="text-warning">';
		echo 'Afbeeldingen moeten in het jpg/jpeg formaat zijn. ';
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

	if (empty($message['amount']))
	{
		echo 'niet opgegeven.';
	}
	else
	{
		echo $message['amount'] . ' ';
		echo $app['config']->get('currency', $app['tschema']);
		echo $message['units'] ? ' per ' . $message['units'] : '';
	}

	echo '</dd>';

	echo '<dt>Van gebruiker: ';
	echo '</dt>';
	echo '<dd>';

	echo $app['account']->link($message['id_user'], $app['pp_ary']);

	echo ' (saldo: <span class="label label-info">';
	echo $balance;
	echo '</span> ';
	echo $app['config']->get('currency', $app['tschema']);
	echo ')';
	echo '</dd>';

	echo '<dt>Plaats</dt>';
	echo '<dd>';
	echo $user['postcode'];
	echo '</dd>';

	echo '<dt>Aangemaakt op</dt>';
	echo '<dd>';
	echo $app['date_format']->get($message['cdate'], 'day', $app['tschema']);
	echo '</dd>';

	echo '<dt>Geldig tot</dt>';
	echo '<dd>';
	echo $app['date_format']->get($message['validity'], 'day', $app['tschema']);
	echo '</dd>';

	if ($app['s_admin'] || $s_owner)
	{
		echo '<dt>Verlengen</dt>';
		echo '<dd>';
		echo btn_extend($app['link'], $app['pp_ary'], $id, 30, '1 maand');
		echo '&nbsp;';
		echo btn_extend($app['link'], $app['pp_ary'], $id, 180, '6 maanden');
		echo '&nbsp;';
		echo btn_extend($app['link'], $app['pp_ary'], $id, 365, '1 jaar');
		echo '</dd>';
	}

	if ($app['count_intersystems'])
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

	$disabled = (!$app['s_schema'] || !count($mail_to) || !count($mail_from) || $s_owner) ? true : false;

	echo '<h3><i class="fa fa-envelop-o"></i> Stuur een reactie naar ';
	echo  $app['account']->link($message['id_user'], $app['pp_ary']);
	echo '</h3>';
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<textarea name="content" rows="6" placeholder="';
	echo $placeholder;
	echo '" ';
	echo 'class="form-control" required';
	echo $disabled ? ' disabled' : '';
	echo '>';
	echo $content ?? '';
	echo '</textarea>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label class="control-label" for="mail_cc">';
	echo '<input type="checkbox" name="cc" ';
	echo 'id="mail_cc" value="1"';
	echo $cc ? ' checked="checked"' : '';
	echo '> Stuur een kopie naar mijzelf';
	echo '</label>';
	echo '</div>';

	echo '<input type="submit" name="mail" ';
	echo 'value="Versturen" class="btn btn-default"';
	echo $disabled ? ' disabled' : '';
	echo '>';
	echo '</form>';

	echo '</div>';
	echo '</div>';
	echo '</div>';

	include __DIR__ . '/../include/footer.php';
	exit;
}

/*
 * list messages
 */

$s_owner = !$app['s_guest']
	&& $app['s_system_self']
	&& isset($filter['uid'])
	&& $app['s_id'] == $filter['uid']
	&& $app['s_id'];

$view = $app['s_view']['messages'];
$p_inline = $app['request']->query->get('inline') ? true : false;

$v_list = $view === 'list' || $p_inline;
$v_extended = $view === 'extended' && !$p_inline;

$params = [
	's'	=> [
		'orderby'	=> $sort['orderby'] ?? 'm.cdate',
		'asc'		=> $sort['asc'] ?? 0,
	],
	'p'	=> [
		'start'		=> $pag['start'] ?? 0,
		'limit'		=> $pag['limit'] ?? 25,
	],
];

$params_sql = $where_sql = $ustatus_sql = [];

if (isset($filter['uid'])
	&& $filter['uid']
	&& !isset($filter['s']))
{
	$filter['fcode'] = $app['account']->str($filter['uid'], $app['tschema']);
}

if (isset($filter['uid']))
{
	$params['f']['uid'] = $filter['uid'];
}

if (isset($filter['q'])
	&& $filter['q'])
{
	$where_sql[] = '(m.content ilike ? or m."Description" ilike ?)';
	$params_sql[] = '%' . $filter['q'] . '%';
	$params_sql[] = '%' . $filter['q'] . '%';
	$params['f']['q'] = $filter['q'];
}

if (isset($filter['fcode'])
	&& $filter['fcode'] !== '')
{
	[$fcode] = explode(' ', trim($filter['fcode']));
	$fcode = trim($fcode);

	$fuid = $app['db']->fetchColumn('select id
		from ' . $app['tschema'] . '.users
		where letscode = ?', [$fcode]);

	if ($fuid)
	{
		$where_sql[] = 'u.id = ?';
		$params_sql[] = $fuid;

		$fcode = $app['account']->str($fuid, $app['tschema']);
		$params['f']['fcode'] = $fcode;
	}
	else
	{
		$where_sql[] = '1 = 2';
	}
}

if (isset($filter['cid'])
	&& $filter['cid'])
{
	$cat_ary = [];

	$st = $app['db']->prepare('select id
		from ' . $app['tschema'] . '.categories
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

	$params['f']['cid'] = $filter['cid'];
}

$filter_valid = isset($filter['valid'])
	&& (isset($filter['valid']['yes']) xor isset($filter['valid']['no']));

if ($filter_valid)
{
	if (isset($filter['valid']['yes']))
	{
		$where_sql[] = 'm.validity >= now()';
		$params['f']['valid']['yes'] = 'on';
	}
	else
	{
		$where_sql[] = 'm.validity < now()';
		$params['f']['valid']['no'] = 'on';
	}
}

$filter_type = isset($filter['type'])
	&& (isset($filter['type']['want']) xor isset($filter['type']['offer']));

if ($filter_type)
{
	if (isset($filter['type']['want']))
	{
		$where_sql[] = 'm.msg_type = 0';
		$params['f']['type']['want'] = 'on';
	}
	else
	{
		$where_sql[] = 'm.msg_type = 1';
		$params['f']['type']['offer'] = 'on';
	}
}

$filter_ustatus = isset($filter['ustatus']) &&
	!(isset($filter['ustatus']['new'])
		&& isset($filter['ustatus']['leaving'])
		&& isset($filter['ustatus']['active']));

if ($filter_ustatus)
{
	if (isset($filter['ustatus']['new']))
	{
		$ustatus_sql[] = '(u.adate > ? and u.status = 1)';
		$params_sql[] = gmdate('Y-m-d H:i:s', $app['new_user_treshold']);
		$params['f']['ustatus']['new'] = 'on';
	}

	if (isset($filter['ustatus']['leaving']))
	{
		$ustatus_sql[] = 'u.status = 2';
		$params['f']['ustatus']['leaving'] = 'on';
	}

	if (isset($filter['ustatus']['active']))
	{
		$ustatus_sql[] = '(u.adate <= ? and u.status = 1)';
		$params_sql[] = gmdate('Y-m-d H:i:s', $app['new_user_treshold']);
		$params['f']['ustatus']['active'] = 'on';
	}

	if (count($ustatus_sql))
	{
		$where_sql[] = '(' . implode(' or ', $ustatus_sql) . ')';
	}
}

if ($app['s_guest'])
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
	from ' . $app['tschema'] . '.messages m, ' .
		$app['tschema'] . '.users u
		where m.id_user = u.id' . $where_sql . '
	order by ' . $params['s']['orderby'] . ' ';

$row_count = $app['db']->fetchColumn('select count(m.*)
	from ' . $app['tschema'] . '.messages m, ' .
		$app['tschema'] . '.users u
	where m.id_user = u.id' . $where_sql, $params_sql);

$query .= $params['s']['asc'] ? 'asc ' : 'desc ';
$query .= ' limit ' . $params['p']['limit'];
$query .= ' offset ' . $params['p']['start'];

$messages = $app['db']->fetchAll($query, $params_sql);

if ($v_extended)
{
	$ids = $imgs = [];

	foreach ($messages as $msg)
	{
		$ids[] = $msg['id'];
	}

	$_imgs = $app['db']->executeQuery('select mp.msgid, mp."PictureFile"
		from ' . $app['tschema'] . '.msgpictures mp
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

$app['pagination']->init('messages', $app['pp_ary'],
	$row_count, $params, $p_inline);

$asc_preset_ary = [
	'asc'	=> 0,
	'fa' 	=> 'sort',
];

$tableheader_ary = [
	'm.msg_type' => array_merge($asc_preset_ary, [
		'lbl' => 'V/A']),
	'm.content' => array_merge($asc_preset_ary, [
		'lbl' => 'Wat']),
];

if (!isset($filter['uid']))
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

if (!$app['s_guest'] && $app['intersystems']->get_count($app['tschema']))
{
	$tableheader_ary += [
		'm.local' => array_merge($asc_preset_ary, [
			'lbl' 	=> 'Zichtbaarheid',
			'data_hide'	=> 'phone, tablet',
		]),
	];
}

$tableheader_ary[$params['s']['orderby']]['asc']
	= $params['s']['asc'] ? 0 : 1;
$tableheader_ary[$params['s']['orderby']]['fa']
	= $params['s']['asc'] ? 'sort-asc' : 'sort-desc';

unset($tableheader_ary['m.cdate']);

$cats = ['' => '-- alle categorieën --'];

$categories = $cat_params  = [];

if (isset($filter['uid']))
{
	$st = $app['db']->executeQuery('select c.*
		from ' . $app['tschema'] . '.categories c, ' .
			$app['tschema'] . '.messages m
		where m.id_category = c.id
			and m.id_user = ?
		order by c.fullname', [$filter['uid']]);
}
else
{
	$st = $app['db']->executeQuery('select *
		from ' . $app['tschema'] . '.categories
		order by fullname');
}

while ($row = $st->fetch())
{
	$cats[$row['id']] = $row['id_parent'] ? ' . . ' : '';
	$cats[$row['id']] .= $row['name'];
	$count_msgs = $row['stat_msgs_offers'] + $row['stat_msgs_wanted'];

	if ($row['id_parent'] && $count_msgs)
	{
		$cats[$row['id']] .= ' (' . $count_msgs . ')';
	}

	$categories[$row['id']] = $row['fullname'];

	$cat_params[$row['id']] = $params;
	$cat_params[$row['id']]['f']['cid'] = $row['id'];
}

if ($app['s_admin'] || $app['s_user'])
{
	if (!$p_inline
		&& ($s_owner || !isset($filter['uid'])))
	{
		$app['btn_top']->add('messages', $app['pp_ary'],
			['add' => '1'], 'Vraag of aanbod toevoegen');
	}

	if (isset($filter['uid']))
	{
		if ($app['s_admin'] && !$s_owner)
		{
			$str = 'Vraag of aanbod voor ';
			$str .= $app['account']->str($filter['uid'], $app['tschema']);

			$app['btn_top']->add('messages', $app['pp_ary'],
				['add' => 1, 'uid' => $filter['uid']], $str);
		}
	}
}

if ($app['s_admin'] && $v_list)
{
	$app['btn_nav']->csv();
}

$filter_panel_open = (($filter['fcode'] ?? false) && !isset($filter['uid']))
	|| $filter_type
	|| $filter_valid
	|| $filter_ustatus;

$filtered = ($filter['q'] ?? false) || $filter_panel_open;

if (isset($filter['uid']))
{
	if ($s_owner && !$p_inline)
	{
		$app['heading']->add('Mijn vraag en aanbod');
	}
	else
	{
		$app['heading']->add($app['link']->link_no_attr('messages', $app['pp_ary'],
			['f' => ['uid' => $filter['uid']]],
			'Vraag en aanbod'));

		$app['heading']->add(' van ');
		$app['heading']->add($app['account']->link($filter['uid'], $app['pp_ary']));
	}
}
else
{
	$app['heading']->add('Vraag en aanbod');
}

if (isset($filter['cid']) && $filter['cid'])
{
	$app['heading']->add(', categorie "' . $categories[$filter['cid']] . '"');
}

$app['heading']->add_filtered($filtered);
$app['heading']->fa('newspaper-o');

if (!$p_inline)
{
	$app['btn_nav']->view('messages', $app['pp_ary'],
		array_merge($params, ['view' => 'list']),
		'Lijst', 'align-justify', $v_list);

	$app['btn_nav']->view('messages', $app['pp_ary'],
		array_merge($params, ['view' => 'extended']),
		'Lijst met omschrijvingen', 'th-list', $v_extended);

	$app['assets']->add([
		'msgs.js',
		'table_sel.js',
		'typeahead',
		'typeahead.js',
	]);

	include __DIR__ . '/../include/header.php';

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

	echo $app['select']->get_options($cats, $filter['cid'] ?? 0);

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

	echo '<div class="col-md-12">';
	echo '<div class="input-group margin-bottom">';

	echo get_checkbox_filter($offerwant_options, 'type', $filter);

	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '<div class="row">';

	$valid_options = [
		'yes'		=> 'Geldig',
		'no'		=> 'Vervallen',
	];

	echo '<div class="col-md-12">';
	echo '<div class="input-group margin-bottom">';

	echo get_checkbox_filter($valid_options, 'valid', $filter);

	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '<div class="row">';

	$user_status_options = [
		'active'	=> 'Niet in- of uitstappers',
		'new'		=> 'Instappers',
		'leaving'	=> 'Uitstappers',
	];

	echo '<div class="col-md-12">';
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
	echo 'data-typeahead="';

	echo $app['typeahead']->get($app['pp_ary'], [['accounts', [
		'status'	=> 'active',
	]]]);

	echo '" ';
	echo 'data-newuserdays="';
	echo $app['config']->get('newuserdays', $app['tschema']);
	echo '" ';
	echo 'name="f[fcode]" id="fcode" placeholder="Account" ';
	echo 'value="';
	echo $filter['fcode'] ?? '';
	echo '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="col-sm-2">';
	echo '<input type="submit" id="filter_submit" ';
	echo 'value="Toon" class="btn btn-default btn-block" ';
	echo 'name="f[s]">';
	echo '</div>';

	echo '</div>';
	echo '</div>';

	$params_form = $params;
	unset($params_form['f']);
	unset($params_form['uid']);
	unset($params_form['p']['start']);

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
}

if ($p_inline)
{
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	$app['heading']->add_inline_btn($app['btn_top']->get());
	echo $app['heading']->get_h3();
}

echo $app['pagination']->get();

if (!count($messages))
{
	echo '<br>';
	echo '<div class="panel panel-default">';
	echo '<div class="panel-body">';
	echo '<p>Er zijn geen resultaten.</p>';
	echo '</div></div>';

	echo $app['pagination']->get();

	if (!$p_inline)
	{
		include __DIR__ . '/../include/footer.php';
	}
	exit;
}

if ($v_list)
{
	echo '<div class="panel panel-info printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-striped ';
	echo 'table-bordered table-hover footable csv" ';
	echo 'id="msgs" data-sort="false">';

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
			$th_params['s'] = [
				'orderby'	=> $key_orderby,
				'asc' 		=> $data['asc'],
			];

			echo $app['link']->link_fa('messages', $app['pp_ary'],
				$th_params, $data['lbl'], [], $data['fa']);
		}
		echo '</th>';
	}

	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($messages as $msg)
	{
		echo '<tr';
		echo strtotime($msg['validity']) < time() ? ' class="danger"' : '';
		echo '>';

		echo '<td>';

		if (!$p_inline && ($app['s_admin'] || $s_owner))
		{
			echo '<input type="checkbox" name="sel_' . $msg['id'] . '" value="1"';
			echo (isset($selected_msgs[$id])) ? ' checked="checked"' : '';
			echo '>&nbsp;';
		}

		echo $msg['msg_type'] ? 'Aanbod' : 'Vraag';
		echo '</td>';

		echo '<td>';

		echo $app['link']->link_no_attr('messages', $app['pp_ary'],
			['id' => $msg['id']], $msg['content']);

		echo '</td>';

		if (!isset($filter['uid']))
		{
			echo '<td>';
			echo $app['account']->link($msg['id_user'], $app['pp_ary']);
			echo '</td>';

			echo '<td>';
			echo $msg['postcode'] ?? '';
			echo '</td>';
		}

		if (!($filter['cid'] ?? false))
		{
			echo '<td>';
			echo $app['link']->link_no_attr('messages', $app['pp_ary'],
				$cat_params[$msg['id_category']],
				$categories[$msg['id_category']]);
			echo '</td>';
		}

		echo '<td>';
		echo $app['date_format']->get($msg['validity'], 'day', $app['tschema']);
		echo '</td>';

		if (!$app['s_guest'] && $app['intersystems']->get_count($app['s_schema']))
		{
			echo '<td>';
			echo $app['access_control']->get_label($msg['local'] ? 'users' : 'interlets');
			echo '</td>';
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

		$sf_owner = $app['s_system_self']
			&& $msg['id_user'] === $app['s_id'];

		$exp = strtotime($msg['validity']) < $time;

		echo '<div class="panel panel-info printview">';
		echo '<div class="panel-body';
		echo ($exp) ? ' bg-danger' : '';
		echo '">';

		echo '<div class="media">';

		if (isset($imgs[$msg['id']]))
		{
			echo '<div class="media-left">';
			echo '<a href="';

			echo $app['link']->context_path('messages', $app['pp_ary'],
				['id' => $msg['id']]);

			echo '">';
			echo '<img class="media-object" src="';
			echo $app['s3_url'] . $imgs[$msg['id']];
			echo '" width="150">';
			echo '</a>';
			echo '</div>';
		}

		echo '<div class="media-body">';
		echo '<h3 class="media-heading">';

		echo $app['link']->link_no_attr('messages', $app['pp_ary'],
			['id' => $msg['id']], $type_str . ': ' . $msg['content']);

		if ($exp)
		{
			echo ' <small><span class="text-danger">';
			echo 'Vervallen</span></small>';
		}

		echo '</h3>';

		echo htmlspecialchars($msg['Description'], ENT_QUOTES);

		echo '</div>';
		echo '</div>';

		echo '</div>';

		echo '<div class="panel-footer">';
		echo '<p><i class="fa fa-user"></i> ';
		echo $app['account']->link($msg['id_user'], $app['pp_ary']);
		echo $msg['postcode'] ? ', postcode: ' . $msg['postcode'] : '';

		if ($app['s_admin'] || $sf_owner)
		{
			echo '<span class="inline-buttons pull-right hidden-xs">';

			echo $app['link']->link_fa('messages', $app['pp_ary'],
				['edit' => $msg['id']], 'Aanpassen',
				['class'	=> 'btn btn-primary btn-xs'],
				'pencil');

			echo $app['link']->link_fa('messages', $app['pp_ary'],
				['del' => $msg['id']], 'Verwijderen',
				['class' => 'btn btn-danger btn-xs'],
				'times');

			echo '</span>';
		}
		echo '</p>';
		echo '</div>';

		echo '</div>';
	}
}

echo $app['pagination']->get();

if ($p_inline)
{
	echo '</div></div>';
}
else if ($v_list)
{
	if (($app['s_admin'] || $s_owner) && count($messages))
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
		echo '<span class="btn btn-default" id="invert_selection">';
		echo 'Selectie omkeren</span>&nbsp;';
		echo '<span class="btn btn-default" id="select_all">';
		echo 'Selecteer alle</span>&nbsp;';
		echo '<span class="btn btn-default" id="deselect_all">';
		echo 'De-selecteer alle</span>';
		echo '</div></div>';

		echo '<h3>Bulk acties met geselecteerd vraag en aanbod</h3>';

		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';

		echo '<ul class="nav nav-tabs" role="tablist">';
		echo '<li class="active"><a href="#extend_tab" ';
		echo 'data-toggle="tab">Verlengen</a></li>';

		if ($app['intersystem_en'])
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
		echo '<label for="extend" class="col-sm-2 control-label">';
		echo 'Verlengen met</label>';
		echo '<div class="col-sm-10">';
		echo '<select name="extend" id="extend" class="form-control">';
		echo $app['select']->get_options($extend_options, '30');
		echo "</select>";
		echo '</div>';
		echo '</div>';

		echo '<input type="submit" value="Verlengen" ';
		echo 'name="extend_submit" class="btn btn-primary">';

		echo $app['form_token']->get_hidden_input();

		echo '</form>';

		echo '</div>';

		if ($app['intersystem_en'])
		{
			echo '<div role="tabpanel" class="tab-pane" id="access_tab">';
			echo '<h3>Zichtbaarheid instellen</h3>';
			echo '<form method="post">';
			echo $app['access_control']->get_radio_buttons('', '', 'admin');
			echo '<input type="submit" value="Aanpassen" ';
			echo 'name="access_submit" class="btn btn-primary">';
			echo $app['form_token']->get_hidden_input();
			echo '</form>';
			echo '</div>';
		}

		echo '</div>';

		echo '<div class="clearfix"></div>';
		echo '</div>';

		echo '</div></div>';
	}

	include __DIR__ . '/../include/footer.php';
}
else if ($v_extended)
{
	include __DIR__ . '/../include/footer.php';
}

function get_checkbox_filter(
	array $checkbox_ary,
	string $filter_id,
	array $filter_ary):string
{
	$out = '';

	foreach ($checkbox_ary as $key => $label)
	{
		$id = 'f_' . $filter_id . '_' . $key;
		$out .= '<label class="checkbox-inline" for="' . $id . '">';
		$out .= '<input type="checkbox" id="' . $id . '" ';
		$out .= 'name="f[' . $filter_id . '][' . $key . ']"';
		$out .= isset($filter_ary[$filter_id][$key]) ? ' checked' : '';
		$out .= '>&nbsp;';
		$out .= '<span class="btn btn-default">';
		$out .= $label;
		$out .= '</span>';
		$out .= '</label>';
	}

	return $out;
}

function get_radio(
	array $radio_ary,
	string $name,
	string $selected,
	bool $required):string
{
	$out = '';

	foreach ($radio_ary as $value => $label)
	{
		$out .= '<label class="radio-inline">';
		$out .= '<input type="radio" name="' . $name . '" ';
		$out .= 'value="' . $value . '"';
		$out .= (string) $value === $selected ? ' checked' : '';
		$out .= $required ? ' required' : '';
		$out .= '>&nbsp;';
		$out .= '<span class="btn btn-default">';
		$out .= $label;
		$out .= '</span>';
		$out .= '</label>';
	}

	return $out;
}

function btn_extend(link $link, array $pp_ary, int $id, int $days, string $label):string
{
	return $link->link('messages', $pp_ary,
		[
			'id' 		=> $id,
			'extend' 	=> $days,
		],
		$label,
		[
			'class' => 'btn btn-default btn-xs',
		]
	);
}
