<?php

$rootpath = './';
$role = 'guest';
$allow_guest_post = true;
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_pagination.php';

$orderby = (isset($_GET['orderby'])) ? $_GET['orderby'] : 'm.cdate';
$asc = (isset($_GET['asc'])) ? $_GET['asc'] : 0;

$limit = ($_GET['limit']) ?: 25;
$start = ($_GET['start']) ?: 0;

$q = (isset($_GET['q'])) ? $_GET['q'] : '';
$cid = (isset($_GET['cid'])) ? $_GET['cid'] : '';
$valid = (isset($_GET['valid'])) ? $_GET['valid'] : 'all';

$id = (isset($_GET['id'])) ? $_GET['id'] : false;
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$add = (isset($_GET['add'])) ? true : false;
$inline = (isset($_GET['inline'])) ? true : false;
$uid = (isset($_GET['uid'])) ? $_GET['uid'] : false;
$img = (isset($_GET['img'])) ? true : false;
$img_del = (isset($_GET['img_del'])) ? $_GET['img_del'] : false;

$images = (isset($_FILES['images'])) ? $_FILES['images'] : null;

$submit = (isset($_POST['zend'])) ? true : false;
$mail = (isset($_POST['mail'])) ? true : false;

$selected_msgs = (isset($_POST['sel'])) ? $_POST['sel'] : array();
$extend_submit = (isset($_POST['extend_submit'])) ? true : false;
$extend = (isset($_POST['extend'])) ? $_POST['extend'] : false;
$access_submit = (isset($_POST['access_submit'])) ? true : false;
$access = (isset($_POST['access'])) ? $_POST['access'] : false;

$bucket = getenv('S3_BUCKET') ?: die('No "S3_BUCKET" env config var in found!');
$bucket_url = 'https://s3.eu-central-1.amazonaws.com/' . $bucket . '/';

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

	$validity_ary = array();

	$rows = $db->executeQuery('select id_user, id, content, validity from messages where id in (?)',
			array($selected_msgs), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));

	foreach ($rows as $row)
	{
		if ($s_user && ($row['id_user'] != $s_id))
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

			$m = array(
				'validity'		=> $validity,
				'mdate'			=> gmdate('Y-m-d H:i:s'),
				'exp_user_warn'	=> 'f',
			);

			if (!$db->update('messages', $m, array('id' => $id)))
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
	}

	if ($access_submit)
	{
		$m = array(
			'local' => ($access == '2') ? 'f' : 't',
			'mdate' => gmdate('Y-m-d H:i:s')
		);

		$db->beginTransaction();
		try
		{
			foreach ($validity_ary as $id => $validity)
			{
				$db->update('messages', $m, array('id' => $id));
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
		}
		catch(Exception $e)
		{
			$db->rollback();
			throw $e;
			$alert->error('Fout bij het opslaan.');
			exit;
		}
	}
	cancel();
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
			AND c.id = m.id_category', array($id));

	if (!$message)
	{
		$alert->error('Bericht niet gevonden.');
		cancel();
	}

	$s_owner = ($s_id == $message['id_user']) ? true : false;

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

	$m = array(
		'validity'		=> $validity,
		'mdate'			=> gmdate('Y-m-d H:i:s'),
		'exp_user_warn'	=> 'f',
	);

	if (!$db->update('messages', $m, array('id' => $id)))
	{
		$alert->error('Fout: ' . $ow_type_the . ' is niet verlengd.');
		cancel($id);
	}

	$alert->success($ow_type_uc_the . ' is verlengd.');
	cancel($id);
}

if ($post)
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));
}

/**
 * post images
 */
if ($post && $images && $id && $img
	&& ($s_admin || $s_owner))
{
	$ret_ary = array();

	$name = $images['name'];
	$size = $images['size'];

	foreach($images['tmp_name'] as $index => $tmpfile)
	{
		$name = $images['name'][$index];
		$size = $images['size'][$index];
		$type = $images['type'][$index];

		if ($type != 'image/jpeg')
		{
			$ret_ary[] = array(
				'name'	=> $name,
				'size'	=> $size,
				'error' => 'ongeldig bestandstype',
			);
			continue;
		}

		if ($size > (200 * 1024))
		{
			$ret_ary[] = array(
				'name'	=> $name,
				'size'	=> $size,
				'error' => 'te groot bestand',
			);
			continue;
		}

		try {
			$filename = $schema . '_m_' . $id . '_' . sha1(time()) . '.jpg';

			$upload = $s3->upload($bucket, $filename, fopen($tmpfile, 'rb'), 'public-read', array(
				'params'	=> array(
					'CacheControl'	=> 'public, max-age=31536000',
				),
			));

			$db->insert('msgpictures', array(
				'msgid'			=> $id,
				'"PictureFile"'	=> $filename));

			$img_id = $db->lastInsertId('msgpictures_id_seq');

			// $size = $s3->get_object_filesize($bucket, $filename);

			log_event($s_id, 'Pict', 'Message-Picture ' . $filename . ' uploaded. Message: ' . $id);

			unlink($tmpfile);

			$ret_ary[] = array(
				'url'			=> $bucket_url . $filename,
				'filename'		=> $filename,
				'name'			=> $name,
				'size'			=> $size,
				'delete_url'	=> 'messages.php?id=' . $id . '&img_del=' . $img_id,
				'delete_type'	=> 'POST',
			);
		}
		catch(Exception $e)
		{ 
			echo $e->getMessage();
			log_event($s_id, 'Pict', 'Upload fail : ' . $e->getMessage());
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

	$imgs = $db->fetchAll('select * from msgpictures where msgid = ?', array($id));

	foreach($imgs as $img)
	{
		$s3->deleteObject(array(
			'Bucket'	=> $bucket,
			'Key'		=> $img['PictureFile'],
		));
	}

	$db->delete('msgpictures', array('msgid' => $id));

	$alert->success('De afbeeldingen voor ' . $ow_type_this . ' zijn verwijderd.');

	cancel($id);
}

/*
 * delete an image
 */
if ($img_del && $post && ctype_digit($img_del))
{
	if (!($msg = $db->fetchAssoc('select m.id_user, p."PictureFile"
		from msgpictures p, messages m
		where p.msgid = m.id
			and p.id = ?', array($img_del))))
	{
		echo json_encode(array('error' => 'Afbeelding niet gevonden.'));
		exit;
	}

	$s_owner = ($msg['id_user'] == $s_id) ? true : false;

	if (!($s_owner || $s_admin))
	{
		echo json_encode(array('error' => 'Onvoldoende rechten om deze afbeelding te verwijderen.'));
		exit;
	}

	$db->delete('msgpictures', array('id' => $img_del));

	$s3->deleteObject(array(
		'Bucket'	=> $bucket,
		'Key'		=> $msg['PictureFile'],
	));

	echo json_encode(array('success' => true));
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

	$images = array();

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

	$str_this_ow = $ow_type . ' "' . aphp('messages', 'id=' . $id, $message['content']) . '"';
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
		$a_img = $bucket_url . $file;

		echo '<div class="col-xs-6 col-md-3">';
		echo '<div class="thumbnail">';
		echo '<img src="' . $a_img . '" class="img-rounded">';

		echo '<div class="caption">';
        echo '<span class="btn btn-danger" data-img-del="' . $img_id . '" ';
        echo 'data-url="' . generate_url('messages', 'img_del=' . $img_id) . '" role="button">';
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

	echo aphp('messages', 'id=' . $id, 'Annuleren', 'btn btn-default'). '&nbsp;';
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

	$systemtag = readconfigfromdb('systemtag');

	$user = readuser($message['id_user']);

	$to = $db->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', array($user['id']));

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

	$subject = '[' . $systemtag . '] - Reactie op je ' . $ow_type . ' ' . $message['content'];

	$mailcontent = 'Beste ' . $user['name'] . "\r\n\r\n";
	$mailcontent .= 'Gebruiker ' . $user_me . ' heeft een reactie op je ' . $ow_type . " verstuurd via de webtoepassing\r\n\r\n";
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
			$status = sendemail($from, $from, $subject . ' (kopie)', $msg . $mailcontent);
		}

		$mailcontent .= "\r\n\r\nInloggen op de website: " . $base_url . "\r\n\r\n";

		if (!$status)
		{
			$status = sendemail($from, $to, $subject, $mailcontent);
		}

		if ($status)
		{
			$alert->error($status);
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
 * delete message
 */
if ($del)
{
	if (!($s_owner || $s_admin))
	{
		$alert->warning('Je hebt onvoldoende rechten om ' . $ow_type_this . ' te verwijderen.');
		cancel($del);
	}

	if($submit)
	{
		$pictures = $db->fetchAll('SELECT * FROM msgpictures WHERE msgid = ?', array($del));

		foreach($pictures as $value)
		{
			$s3->deleteObject(array(
				'Bucket' => $bucket,
				'Key'    => $value['PictureFile'],
			));
		}

		$db->delete('msgpictures', array('msgid' => $del));

		if ($db->delete('messages', array('id' => $del)))
		{
			$column = 'stat_msgs_';
			$column .= ($message['msg_type']) ? 'offers' : 'wanted';

			$db->executeUpdate('update categories
				set ' . $column . ' = ' . $column . ' - 1
				where id = ?', array($message['id_category']));

			$alert->success(ucfirst($ow_type_this) . ' is verwijderd.');
			cancel();
		}

		$alert->error(ucfirst($ow_type_this) . ' is niet verwijderd.');
	}

	$h1 = ucfirst($ow_type_this) . ' ';
	$h1 .= aphp('messages', 'id=' . $del, $message['content']);
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

	echo aphp('messages', 'id=' . $del, 'Annuleren', 'btn btn-default'). '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
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
		$errors = array();

		$validity = $_POST['validity'];

		if (!ctype_digit($validity))
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
					and status in (1, 2)', array($user_letscode));
			if (!$user)
			{
				$errors[] = 'Ongeldige letscode.' . $user_letscode;
			}
		}

		$msg = array(
			'validity'		=> $_POST['validity'],
			'vtime'			=> $vtime,
			'content'		=> $_POST['content'],
			'"Description"'	=> $_POST['description'],
			'msg_type'		=> $_POST['msg_type'],
			'id_user'		=> ($s_admin) ? (int) $user['id'] : $s_id,
			'id_category'	=> $_POST['id_category'],
			'amount'		=> $_POST['amount'],
			'units'			=> $_POST['units'],
			'local'			=> ($_POST['local']),
		);

		if (!ctype_digit($msg['amount']) && $msg['amount'] != '')
		{
			$errors[] = 'De (richt)prijs in ' . readconfigfromdb('currency') . ' moet nul of een positief getal zijn.';
		}

		if (!$msg['id_category'])
		{
			$errors[] = 'Geieve een categorie te selecteren.';
		}

		if (!$msg['content'])
		{
			$errors[] = 'Vul inhoud in!';

			if(!$db->fetchColumn('select id from categories where id = ?', array($msg['id_category'])))
			{
				$errors[] = 'Categorie bestaat niet!';
			}
		}

		if(strlen($msg['units']) > 15)
		{
			$errors[] = '"Per (uur, stuk, ...)" mag maximaal 15 tekens lang zijn.';
		}

		if(!($db->fetchColumn('select id from users where id = ? and status <> 0', array($msg['id_user']))))
		{
			$errors[] = 'Gebruiker bestaat niet!';
		}

		if (count($errors))
		{
			$alert->error(implode('<br>', $errors));
		}
		else if ($add)
		{
			$msg['cdate'] = gmdate('Y-m-d H:i:s');
			$msg['validity'] = $msg['vtime'];

			unset($msg['vtime'], $msg['description']);

			if (empty($msg['amount']))
			{
				unset($msg['amount']);
			}

			if ($db->insert('messages', $msg))
			{
				$id = $db->lastInsertId('messages_id_seq');

				$stat_column = 'stat_msgs_';
				$stat_column .= ($msg['msg_type']) ? 'offers' : 'wanted';

				$db->executeUpdate('update categories set ' . $stat_column . ' = ' . $stat_column . ' + 1 where id = ?', array($msg['id_category']));

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
				$db->update('messages', $msg, array('id' => $edit));

	//			$db->update('messages', array('"Description"' => $description), array('id' => $id));

				if ($msg['msg_type'] != $msg['msg_type'] || $msg['id_category'] != $msg['id_category'])
				{
					$column = 'stat_msgs_';
					$column .= ($msg['msg_type']) ? 'offers' : 'wanted';

					$db->executeUpdate('update categories
						set ' . $column . ' = ' . $column . ' - 1
						where id = ?', array($msg['id_category']));

					$column = 'stat_msgs_';
					$column .= ($msg['msg_type']) ? 'offers' : 'wanted';

					$db->executeUpdate('update categories
						set ' . $column . ' = ' . $column . ' + 1
						where id = ?', array($msg['id_category']));
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
	}
	else if ($edit)
	{
		$msg =  $db->fetchAssoc('select m.*,
			m."Description" as description
			from messages m
			where m.id = ?', array($edit));
		$msg['description'] = $msg['Description'];
		unset($msg['Description']);

		$rev = round((strtotime($msg['validity']) - time()) / (86400));
		$msg['validity'] = ($rev < 1) ? 0 : $rev;

		$user = readuser($msg['id_user']);

		$user_letscode = $user['letscode'] . ' ' . $user['name'];
	}
	else if ($add)
	{
		$msg = array(
			'validity'		=> '',
			'content'		=> '',
			'description'	=> '',
			'msg_type'		=> '1',
			'id_user'		=> $s_id,
			'id_category'	=> '',
			'amount'		=> '',
			'units'			=> '',
			'local'			=> 0,
		);

		$uid = (isset($_GET['uid']) && $s_admin) ? $_GET['uid'] : $s_id;

		$user = readuser($uid);

		$user_letscode = $user['letscode'] . ' ' . $user['name'];
	}

	$cat_list = array('' => '');

	$rs = $db->prepare('SELECT id, fullname  FROM categories WHERE leafnote=1 order by fullname');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$cat_list[$row['id']] = $row['fullname'];
	}

	$currency = readconfigfromdb('currency');

	array_walk($msg, function(&$value, $key){ $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); });

	$top_buttons .= aphp('messages', '', 'Lijst', 'btn btn-default', 'Alle vraag en aanbod', 'newspaper-o', true);
	$top_buttons .= aphp('messages', 'uid=' . $s_id, 'Mijn vraag en aanbod', 'btn btn-default', 'Mijn vraag en aanbod', 'user', true);

	$includejs = '
		<script src="' . $cdn_typeahead . '"></script>
		<script src="' . $rootpath . 'js/msg_edit.js"></script>';

	$h1 = ($add) ? 'Nieuw Vraag of Aanbod toevoegen' : 'Vraag of Aanbod aanpassen';
	$fa = 'newspaper-o';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="msg_type" class="col-sm-2 control-label">Vraag/Aanbod</label>';
	echo '<div class="col-sm-10">';
	echo '<select name="msg_type" id="msg_type" class="form-control" required>';
	render_select_options(array('1' => 'Aanbod', '0' => 'Vraag'), $msg['msg_type']);
	echo "</select>";
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="content" class="col-sm-2 control-label">Wat</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="content" name="content" ';
	echo 'value="' . $msg['content'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
	echo '<div class="col-sm-10">';
	echo '<textarea name="description" class="form-control" id="description" rows="4">';
	echo $msg['description'];
	echo '</textarea>';
	echo '</div>';
	echo '</div>';

	if($s_admin)
	{
		echo '<div class="form-group">';
		echo '<label for="user_letscode" class="col-sm-2 control-label">';
		echo '<span class="label label-info">Admin</span> Gebruiker</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="user_letscode" name="user_letscode" ';
		echo 'data-letsgroup-id="self" '; //data-thumbprint="' . time() . '" ';
		echo 'data-url="' . $rootpath . 'ajax/active_users.php?' . get_session_query_param() . '" ';
		echo 'value="' . $user_letscode . '" required>';
		echo '</div>';
		echo '</div>';
	}

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

	echo '<div class="form-group">';
	echo '<label for="local" class="col-sm-2 control-label">Zichtbaarheid</label>';
	echo '<div class="col-sm-10">';
	echo '<select name="local" id="local" class="form-control" required>';
	render_select_options(array('1' => 'leden', '0' => 'interlets'), $msg['local']);
	echo "</select>";
	echo '</div>';
	echo '</div>';

	$btn = ($edit) ? 'primary' : 'success';

	echo aphp('messages', 'id=' . $id, 'Annuleren', 'btn btn-default'). '&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-' . $btn . '">';

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
	$user = readuser($message['id_user']);

	$to = $db->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', array($user['id']));

	$balance = $user['saldo'];

	$images = array();

	$st = $db->prepare('select id, "PictureFile" from msgpictures where msgid = ?');
	$st->bindValue(1, $id);
	$st->execute();

	while ($row = $st->fetch())
	{
		$images[$row['id']] = $row['PictureFile'];
	}

	$and_local = ($s_guest) ? ' and local = false ' : '';

	$prev = $db->fetchColumn('select id
		from messages
		where id > ?
		' . $and_local . '
		order by id asc
		limit 1', array($id));

	$next = $db->fetchColumn('select id
		from messages
		where id < ?
		' . $and_local . '
		order by id desc
		limit 1', array($id));

	$currency = readconfigfromdb('currency');

	$title = $message['content'];

	$contacts = $db->fetchAll('select c.*, tc.abbrev
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and c.flag_public = 1', array($user['id']));

	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_fileupload_css . '" />';

	$includejs = '<script src="' . $cdn_jssor_slider_mini_js . '"></script>
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
		$top_buttons .= aphp('messages', 'add=1', 'Toevoegen', 'btn btn-success', 'Vraag of aanbod toevoegen', 'plus', true);

		if ($s_admin || $s_owner)
		{
			$top_buttons .= aphp('messages', 'edit=' . $id, 'Aanpassen', 'btn btn-primary', $ow_type_uc . ' aanpassen', 'pencil', true);
			$top_buttons .= aphp('messages', 'del=' . $id, 'Verwijderen', 'btn btn-danger', $ow_type_uc . ' verwijderen', 'times', true);
		}

		if ($message['msg_type'] == 1 && !$s_owner)
		{
			$top_buttons .= aphp('transactions', 'add=1&mid=' . $id, 'Transactie', 'btn btn-warning', 'Transactie voor dit aanbod', 'exchange', true);
		}
	}

	$top_buttons .= aphp('messages', '', 'Lijst', 'btn btn-default', 'Alle vraag en aanbod', 'newspaper-o', true);

	if ($prev)
	{
		$top_buttons .= aphp('messages', 'id=' . $prev, 'Vorige', 'btn btn-default', 'Vorige', 'chevron-up', true);
	}

	if ($next)
	{
		$top_buttons .= aphp('messages', 'id=' . $next, 'Volgende', 'btn btn-default', 'Volgende', 'chevron-down', true);
	}

	if ($s_user || $s_admin)
	{
		$top_buttons .= aphp('messages', 'uid=' . $s_id, 'Mijn vraag en aanbod', 'btn btn-default', 'Mijn vraag en aanbod', 'user', true);
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
	echo 'data-bucket-url="' . $bucket_url . '" ';
	echo 'data-images="' . implode(',', $images) . '">';
	echo '</div>';

	echo '</div>'; // panel-body

	if ($s_admin || $s_owner)
	{
		echo '<div class="panel-footer"><span class="btn btn-success fileinput-button">';
		echo '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
		echo '<input id="fileupload" type="file" name="images[]" ';
		echo 'data-url="' . generate_url('messages', 'img=1&id=' . $id) . '" ';
		echo 'data-data-type="json" data-auto-upload="true" ';
		echo 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
		echo 'data-max-file-size="999000" ';
		echo 'multiple></span>&nbsp;';

		echo aphp('messages', 'img_del=all&id=' . $id, 'Afbeeldingen verwijderen', 'btn btn-danger', false, 'times', false,
			array('id' => 'btn_remove', 'style' => 'display:none;'));

		echo '<p class="text-warning">Afbeeldingen moeten in het jpg/jpeg formaat zijn. ';
		echo 'Je kan ook afbeeldingen hierheen verslepen.</p>';
		echo '</div>';
	}

	echo '</div>';

	echo '</div>';

//	echo '</div></div>';
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
	echo '<dd>' . $message['cdate'] . '</dd>';

	echo '<dt>Geldig tot</dt>';
	echo '<dd>' . $message['validity'] . '</dd>';

	if ($s_admin || $s_owner)
	{
		echo '<dt>Verlengen</dt>';
		echo '<dd>' . aphp('messages', 'id=' . $id . '&extend=30', '1 maand', 'btn btn-default btn-xs') . '&nbsp;';
		echo aphp('messages', 'id=' . $id . '&extend=180', '6 maanden', 'btn btn-default btn-xs') . '&nbsp;';
		echo aphp('messages', 'id=' . $id . '&extend=365', '1 jaar', 'btn btn-default btn-xs') . '</dd>';
	}

	$access = $acc_ary[($message['local']) ? 1 : 2];

	echo '<dt>Zichtbaarheid</dt>';
	echo '<dd><span class="label label-' . $access[1] . '">' . $access[0] . '</span></dd>';

	echo '</dl>';

	echo '</div>';
	echo '</div>'; // panel

	echo '</div>'; //col-md-6
	echo '</div>'; //row

	echo '<div id="contacts" data-uid="' . $message['id_user'] . '" ';
	echo 'data-url="' . $rootpath . 'contacts.php?inline=1&uid=' . $message['id_user'];
	echo '&' . get_session_query_param() . '"></div>';

	// response form

	if ($s_guest && !isset($s_interlets['mail']))
	{
		$placeholder = 'Als gast kan je niet het reactieformulier gebruiken.';
	}
	else if ($s_owner)
	{
		$placeholder = 'Je kan geen reacties op je eigen berichten sturen.';
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

$s_owner = ($s_id == $uid && $s_id && $uid) ? true : false;

$sql_bind = array();
$and_where = '';
$params = array();

$gmdate = gmdate('Y-m-d H:i:s');

if ($uid)
{
	$and_where .= ' and u.id = ? ';
	$sql_bind[] = $uid;
	$params['uid'] = $uid;
}

if ($q)
{
	$and_where .= ' and m.content ilike ? ';
	$sql_bind[] = '%' . $q . '%';
	$params['q'] = $q;
}

if ($cid)
{
	$and_where .= ' and m.id_category = ? ';
	$sql_bind[] = $cid;
	$params['cid'] = $cid;
}

if ($valid && $valid != 'all')
{
	$operator_valid = ($valid == 'f') ? '<' : '>=';
	$and_where .= ' and m.validity ' . $operator_valid . ' now()';
	$params['valid'] = $valid;
}

if ($s_guest)
{
	$and_where = ' and local = false ';
}

$query = 'select m.*, u.postcode
	from messages m, users u
		where m.id_user = u.id
			and u.status in (1, 2) ' .
	$and_where . '
	order by ' . $orderby . ' ';

$row_count = $db->fetchColumn('select count(m.*)
	from messages m, users u
	where m.id_user = u.id
		and u.status in (1, 2)
		' . $and_where, $sql_bind);

$query .= ($asc) ? 'asc ' : 'desc ';
$query .= ' limit ' . $limit . ' offset ' . $start;

$messages = $db->fetchAll($query, $sql_bind);

$params['orderby'] = $orderby;
$params['asc'] = $asc;

$pagination = new pagination(array(
	'limit' 		=> $limit,
	'start' 		=> $start,
	'entity'		=> 'messages',
	'params'		=> $params,
	'row_count'		=> $row_count,
));

$asc_preset_ary = array(
	'asc'	=> 0,
	'indicator' => '',
);

$tableheader_ary = array(
	'm.msg_type' => array_merge($asc_preset_ary, array(
		'lbl' => 'V/A')),
	'm.content' => array_merge($asc_preset_ary, array(
		'lbl' => 'Wat')),
);

if (!$uid)
{
	$tableheader_ary += array(
		'u.name'	=> array_merge($asc_preset_ary, array(
			'lbl' 		=> 'Wie',
			'data_hide' => 'phone,tablet',
		)),
		'u.postcode'	=> array_merge($asc_preset_ary, array(
			'lbl' 		=> 'Postcode',
			'data_hide'	=> 'phone,tablet',
		)),
	);
}

if (!$cid)
{
	$tableheader_ary += array(
		'm.id_category' => array_merge($asc_preset_ary, array(
			'lbl' 		=> 'Categorie',
			'data_hide'	=> 'phone, tablet',
		)),
	);
}

$tableheader_ary += array(
	'm.validity' => array_merge($asc_preset_ary, array(
		'lbl' 	=> 'Geldig tot',
		'data_hide'	=> 'phone, tablet',
	)),
	'm.local' => array_merge($asc_preset_ary, array(
		'lbl' 	=> 'Zichtbaarheid',
		'data_hide'	=> 'phone, tablet',
	)),	
);

$tableheader_ary[$orderby]['asc'] = ($asc) ? 0 : 1;
$tableheader_ary[$orderby]['indicator'] = ($asc) ? '-asc' : '-desc';

unset($tableheader_ary['m.cdate']);

$cats = array('' => '-- alle categorieën --');

$categories = $cat_params  = array();

if ($uid)
{
	$st = $db->executeQuery('select c.*
		from categories c, messages m
		where m.id_category = c.id
			and m.id_user = ?
		order by c.fullname', array($uid));
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
}

if ($s_admin || $s_user)
{
	if (!$inline)
	{
		$top_buttons .= aphp('messages', 'add=1', 'Toevoegen', 'btn btn-success', 'Vraag of aanbod toevoegen', 'plus', true);
	}

	if ($uid)
	{
		if ($s_admin && !$s_owner)
		{
			$str = 'Vraag of aanbod voor ' . link_user($uid, null, false);
			$top_buttons .= aphp('messages', 'add=1&uid=' . $uid, $str, 'btn btn-success', $str, 'plus', true);
		}

		if (!$inline)
		{
			$top_buttons .= aphp('messages', '', 'Lijst', 'btn btn-default', 'Lijst alle vraag en aanbod', 'newspaper-o', true);
		}
	}
	else
	{
		$top_buttons .= aphp('messages', 'uid=' . $s_id, 'Mijn vraag en aanbod', 'btn btn-default', 'Mijn vraag en aanbod', 'user', true);
	}
}

if ($s_admin)
{
	$top_right .= '<a href="#" class="csv">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv</a>';
}

$h1 = ($uid && $inline) ? aphp('messages', 'uid=' . $uid,  'Vraag en aanbod') : 'Vraag en aanbod';
$h1 .= ($uid) ? ' van ' . link_user($uid) : '';
$h1 = (!$s_admin && $s_owner) ? 'Mijn vraag en aanbod' : $h1;
$h1 .= ($cid) ? ', categorie "' . $categories[$cid] . '"' : '';

$fa = 'newspaper-o';

if (!$inline)
{
	$includejs = '<script src="' . $rootpath . 'js/csv.js"></script>
		<script src="' . $rootpath . 'js/msgs.js"></script>
		<script src="' . $rootpath . 'js/table_sel.js"></script>';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="get" class="form-horizontal">';

	echo '<div class="row">';
	echo '<div class="col-xs-12">';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-search"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="q" value="' . $q . '" name="q">';
	echo '</div>';
	echo '</div></div><br>';

	echo '<div class="row">';
	echo '<div class="col-xs-12">';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-clone"></i>';
	echo '</span>';
	echo '<select class="form-control" id="cid" name="cid">';
	render_select_options($cats, $cid);
	echo '</select>';
	echo '</div>';
	echo '</div>';
	echo '</div>';

	$params_form = $params;
	unset($params_form['cid'], $params_form['q']);

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

	$nav_tabs = array(
		'all'	=> array(
			'color'		=> 'white',
			'lbl'		=> 'Alle',
		),
		't'		=> array(
			'color'		=> 'white',
			'lbl'		=> 'Geldig',
		),
		'f'		=> array(
			'color'		=> 'danger',
			'lbl'		=> 'Vervallen',
		),
	);

	echo '<ul class="nav nav-tabs" id="nav-tabs">';

	$nav_params = $params;
	
	foreach ($nav_tabs as $key => $tab)
	{
		$nav_params['valid'] = $key;
		echo '<li';
		echo ($valid == $key) ? ' class="active"' : '';
		echo '>';
		echo aphp('messages', $nav_params, $tab['lbl'], 'bg-' . $tab['color']) . '</li>';
	}
	echo '</ul>';

	echo ($s_admin || $s_owner) ? '<form method="post" class="form-horizontal">' : '';
}
else
{
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	echo '<h3><i class="fa fa-newspaper-o"></i> ' . $h1;
	echo '<span class="inline-buttons">' . $top_buttons . '</span>';
	echo '</h3>';
}

$pagination->render();

echo '<div class="panel panel-info printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-bordered table-hover footable csv" ';
echo 'id="msgs" data-sort="false">';

echo '<thead>';
echo '<tr>';

$th_params = $params;

foreach ($tableheader_ary as $key_orderby => $data)
{
	echo '<th';
	echo ($data['data_hide']) ? ' data-hide="' . $data['data_hide'] . '"' : '';
	echo '>';
	if ($data['no_sort'])
	{
		echo $data['lbl'];
	}
	else
	{
		$th_params['orderby'] = $key_orderby;
		$th_params['asc'] = $data['asc'];

		echo aphp('messages', $th_params, array($data['lbl'] . '&nbsp;<i class="fa fa-sort' . $data['indicator'] . '"></i>'));
	}
	echo '</th>';
}

echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($messages as $msg)
{
	$del = (strtotime($msg['validity']) < time()) ? true : false;

	echo '<tr';
	echo ($del) ? ' class="danger"' : '';
	echo '>';

	echo '<td>';

	if (!$inline && ($s_admin || $s_owner))
	{
		echo '<input type="checkbox" name="sel[' . $msg['id'] . ']" value="1"';
		echo ($selected_msgs[$id]) ? ' checked="checked"' : '';
		echo '>&nbsp;';
	}

	echo ($msg['msg_type']) ? 'Aanbod' : 'Vraag';
	echo '</td>';

	echo '<td>';
	echo aphp('messages', 'id=' . $msg['id'], $msg['content']);
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
	echo $msg['validity'];
	echo '</td>';

	if (!$s_guest)
	{
		$access = $acc_ary[($msg['local']) ? 1 : 2];
		echo '<td><span class="label label-' . $access[1] . '">' . $access[0] . '</span></td>';
	}

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '</div>';
echo '</div>';

$pagination->render();

if ($inline)
{
	echo '</div></div>';
}
else
{
	if (($s_admin || $s_owner) && count($messages))
	{
		$extend_options = array(
			'7'		=> '1 week',
			'14'	=> '2 weken',
			'30'	=> '1 maand',
			'60'	=> '2 maanden',
			'180'	=> '6 maanden',
			'365'	=> '1 jaar',
			'730'	=> '2 jaar',
			'1825'	=> '5 jaar',
		);

		unset($access_options[0]);

		echo '<div class="panel panel-default" id="actions">';
		echo '<div class="panel-heading">';
		echo '<span class="btn btn-default" id="select_all">Selecteer alle</span>&nbsp;';
		echo '<span class="btn btn-default" id="deselect_all">De-selecteer alle</span>';
		echo '</div></div>';
		echo '<h3>Acties met geselecteerd vraag en aanbod</h3>';
		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';

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

		echo '<div class="form-group">';
		echo '<label for="access" class="col-sm-2 control-label">Zichtbaarheid</label>';
		echo '<div class="col-sm-10">';
		echo '<select name="access" id="access" class="form-control">';
		render_select_options($access_options, 2);
		echo "</select>";
		echo '</div>';
		echo '</div>';

		echo '<input type="submit" value="Aanpassen" name="access_submit" class="btn btn-primary">';

		echo '</div>';
		echo '</div>';

		echo '<div class="clearfix"></div>';
		echo '</div>';
		echo '</div>';
		echo '</div></div>';
		echo '</form>';
	}

	include $rootpath . 'includes/inc_footer.php';
}

function cancel($id = null)
{
	global $uid;

	$param = ($uid && !$id) ? 'uid=' . $uid : (($id) ? 'id=' . $id : '');

	header('Location: ' . generate_url('messages', $param));
	exit;
}

