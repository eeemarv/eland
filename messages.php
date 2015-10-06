<?php
ob_start();
$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$id = ($_GET['id']) ?: false;
$del = ($_GET['del']) ?: false;
$edit = ($_GET['edit']) ?: false;
$add = ($_GET['add']) ?: false;
$inline = ($_GET['inline']) ? true : false;
$uid = ($_GET['uid']) ?: false;

$upload = ($_FILES['files']) ?: null;

$q = ($_GET['q']) ?: '';
$hsh = ($_GET['hsh']) ?: '';
$cid = ($_GET['cid']) ?: '';
$cat_hsh = ($_GET['cat_hsh']) ?: '';

$extend = ($_GET['extend']) ?: false;

$submit = ($_POST['zend']) ? true : false;
$mail = ($_POST['mail']) ? true : false;

$post = ($_SERVER['REQUEST_METHOD'] === 'POST') ? true : false;

if ($id || $edit || $del)
{
	$id = ($id) ?: (($edit) ?: $del);

	$message = $db->fetchAssoc('SELECT m.*,
			c.id as cid,
			c.fullname as catname
		FROM messages m, categories c
		WHERE m.id = ?
			AND c.id = m.id_category', array($id));

	$s_owner = ($s_id == $message['id_user']) ? true : false;
}

if($post && $upload & $id
	&& is_array($upload['tmp_name'])
	&& ($s_admin || $s_owner)
	&& !$s_guest)
{
	$ret_ary = array();

	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));
	$bucket = getenv('S3_BUCKET') ?: die('No "S3_BUCKET" env config var in found!');
	
	foreach($upload['tmp_name'] as $index => $value)
	{
		$tmpfile = $upload['tmp_name'][$index];
/*
		$imagine = new Imagine\Imagick\Imagine();
		$image = $imagine->open($tmpfile);
		$image->resize(new Box(400, 400), ImageInterface::FILTER_LANCZOS)
		   ->save($tmpfile);
*/
		try {
			$filename = $schema . '_m_' . $msgid . '_' . sha1(time()) . '.jpg';
			
			$upload = $s3->upload($bucket, $filename, fopen($tmpfile, 'rb'), 'public-read', array(
				'params'	=> array(
					'CacheControl'	=> 'public, max-age=31536000',
				),
			));

			$db->insert('msgpictures', array(
				'msgid'			=> $msgid,
				'"PictureFile"'	=> $filename));
			log_event($s_id, 'Pict', 'Message-Picture ' . $file . 'uploaded. Message: ' . $msgid);

			unlink($tmpfile);
			$alert->success('De afbeelding is opgeladen.');

			$ret_ary[$index] = $filename;
		}
		catch(Exception $e)
		{ 
			$alert->error( 'Upladen afbeelding mislukt.');
			echo $e->getMessage();
			log_event($s_id, 'Pict', 'Upload fail : ' . $e->getMessage());
		}
	}

	echo json_encode($ret_ary);
	cancel($id);
	exit;
}

if ($mail && $post && $id)
{
	$content = $_POST['content'];
	$cc = $_POST['cc'];

	$systemtag = readconfigfromdb('systemtag');

	$me = readuser($s_id);

	$from = $db->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', array($s_id));

	$my_contacts = $db->fetchAll('select c.value, tc.abbrev
		from contact c, type_contact tc
		where c.flag_public = 1
			and c.id_user = ?
			and c.id_type_contact = tc.id', array($s_id));

	$va = ($message['msg_type']) ? 'aanbod' : 'vraag';

	$subject = '[eLAS-' . $systemtag . '] - Reactie op je ' . $va . ' ' . $message['content'];

	if($cc)
	{
		$to =  $to . ', ' . $from;
	}

	$mailcontent = 'Beste ' . $user['name'] . "\r\n\n";
	$mailcontent .= '-- ' . $me['name'] . ' heeft een reactie op je ' . $va . " verstuurd via eLAS --\r\n\n";
	$mailcontent .= $content . "\n\n";
	$mailcontent .= "Om te antwoorden kan je gewoon reply kiezen of de contactgegevens hieronder gebruiken\n";
	$mailcontent .= 'Contactgegevens van ' . $me['name'] . ":\n";

	foreach($my_contacts as $value)
	{
		$mailcontent .= '* ' . $value['abbrev'] . "\t" . $value['value'] ."\n";
	}

	if ($content)
	{
		$status = sendemail($from, $to, $subject, $mailcontent, 1);

		if ($status)
		{
			$alert->error($status);
		}
		else
		{
			$alert->success('Mail verzonden.');
			$content = '';
		}
	}
	else
	{
		$alert->error('Fout: leeg bericht. Mail niet verzonden.');
	}
	cancel($id);
}


if ($del && !$s_guest)
{

}

if (($edit || $add) && !$s_guest)
{
	if (!($s_admin || $s_user) && $add)
	{
		$alert->error('Je hebt onvoldoende rechten om een vraag of aanbod toe te voegen.');
		cancel();
	}

	if (!($s_admin || $s_owner) && $edit)
	{
		$alert->error('Je hebt onvoldoende rechten om dit vraag of aanbod aan te passen.');
		cancel($edit);
	}

	if ($submit)
	{
		$errors = array();

		$validity = (int) $_POST['validity'];

		$vtime = time() + ($validity * 30 * 86400);
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
		);

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

//			$description = $msg['description'];

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

				// Description column is mixed case.
//				$db->update('messages', array('"Description"' => $description), array('id' => $id));

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

			error_log(implode(' ----- ', $msg));

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

		$rev = round((strtotime($msg['validity']) - time()) / (30 * 86400));
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
		);

		$uid = (isset($_GET['uid']) && $s_admin) ? $_GET['uid'] : $s_id;

		$user = readuser($uid);

		$user_letscode = $user['letscode'] . ' ' . $user['name'];
	}

	$letsgroup_id = $db->fetchColumn('SELECT id
		FROM letsgroups
		WHERE apimethod = \'internal\'');

	$cat_list = array('' => '');

	$rs = $db->prepare('SELECT id, fullname  FROM categories WHERE leafnote=1 order by fullname');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$cat_list[$row['id']] = $row['fullname'];
	}

	$currency = readconfigfromdb('currency');

	array_walk($msg, function(&$value, $key){ $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); });

	$top_buttons = '<a href="' . $rootpath . 'messages.php" class="btn btn-default"';
	$top_buttons .= ' title="Alle Vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

	$top_buttons .= '<a href="' . $rootpath . 'messages.php?uid=' . $s_id . '" class="btn btn-default"';
	$top_buttons .= ' title="Mijn vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn vraag en aanbod</span></a>';

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
		echo '<span class="label label-default">Admin</span> Gebruiker</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="user_letscode" name="user_letscode" ';
		echo 'data-letsgroup-id="' . $letsgroup_id . '" ';
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
	echo '<label for="validity" class="col-sm-2 control-label">Geldigheid in maanden</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="number" class="form-control" id="validity" name="validity" ';
	echo 'value="' . $msg['validity'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="amount" class="col-sm-2 control-label">Aantal ' . $currency . '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="number" class="form-control" id="amount" name="amount" ';
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

	$btn = ($edit) ? 'primary' : 'success';
	echo '<a href="' . $rootpath . 'userdetails/mymsg_overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-' . $btn . '">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}


if ($id)
{
	$user = readuser($message['id_user']);

	$to = $db->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'', array($user['id']));

	$balance = $user['saldo'];

	$msgpictures = $db->fetchAll('select * from msgpictures where msgid = ?', array($msgid));
	$currency = readconfigfromdb('currency');

	$title = $message['content'];

	$contacts = $db->fetchAll('select c.*, tc.abbrev
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and c.flag_public = 1', array($user['id']));

	$includejs = '<script src="' . $cdn_jssor_slider_mini_js . '"></script>
		<script src="' . $cdn_jquery_ui_widget . '"></script>
		<script src="' . $cdn_load_image . '"></script>
		<script src="' . $cdn_canvas_to_blob . '"></script>
		<script src="' . $cdn_jquery_iframe_transport . '"></script>
		<script src="' . $cdn_jquery_fileupload . '"></script>
		<script src="' . $cdn_jquery_fileupload_process . '"></script>
		<script src="' . $cdn_jquery_fileupload_image . '"></script>
		<script src="' . $cdn_jquery_fileupload_validate . '"></script>
		<script src="' . $rootpath . 'js/msg.js"></script>';

	$top_buttons = '';

	if ($s_user || $s_admin)
	{
		$top_buttons .= '<a href="' . $rootpath . 'messages.php?add=1" class="btn btn-success"';
		$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

		if ($s_admin || $s_owner)
		{
			$top_buttons .= '<a href="' . $rootpath . 'messages.php?edit=' . $id . '" ';
			$top_buttons .= 'class="btn btn-primary"';
			$top_buttons .= ' title="Vraag of aanbod aanpassen"><i class="fa fa-pencil"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

			$top_buttons .= '<a href="' . $rootpath . 'messages.php?del=' . $id . '" ';
			$top_buttons .= 'class="btn btn-danger"';
			$top_buttons .= ' title="Vraag of aanbod verwijderen"><i class="fa fa-times"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';
		}

		if ($message['msg_type'] == 1 && !$s_owner)
		{
			$top_buttons .= '<a href="' . $rootpath . 'transactions.php?add=1&mid=' . $id . '" class="btn btn-warning"';
			$top_buttons .= ' title="Transactie voor dit aanbod toevoegen"><i class="fa fa-exchange"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Transactie</span></a>';
		}

		$top_buttons .= '<a href="' . $rootpath . 'messages.php" class="btn btn-default"';
		$top_buttons .= ' title="Alle Vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

		$top_buttons .= '<a href="' . $rootpath . 'messages.php?uid=' . $s_id . '" class="btn btn-default"';
		$top_buttons .= ' title="Mijn vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn vraag en aanbod</span></a>';
	}

	$h1 = ($message['msg_type']) ? 'Aanbod' : 'Vraag';
	$h1 .= ': ' . htmlspecialchars($message['content'], ENT_QUOTES);
	$fa = 'newspaper-o';

	include $rootpath.'includes/inc_header.php';

	echo '<div class="row">';

	if($s_admin || $s_owner)
	{
		$add_img = '<div class="upload-wrapper">
	<div id="error_output"></div>
		<div id="files" class="files"></div>
	</div>';
		// $btn_add_img = "<script type='text/javascript'>function AddPic () { OpenTBox('" . $myurl ."'); } </script>";
		$add_img .= '<input type="file" name="files[]" class="btn btn-success" ';
		$add_img .= 'title="Afbeelding toevoegen" multiple id="fileupload">';
	//	$add_img .= '<i class="fa fa-plus"></i>';
	//	$add_img .= '<span class="hidden-xs hidden-sm"> Afbeelding toevoegen</span>';
	}

	$add_img = ($add_img) ? '<p>' . $add_img . '</p>' : '';

	if ($msgpictures)
	{
		echo '<div class="col-md-6">';
		echo '<div class="col-lg-8 col-lg-offset-2 text-center">';
		echo '<div id="slider1_container" style="position: relative; 
						top: 0px; left: 0px; width: 800px; height: 600px;">';
		echo '<div u="slides" style="cursor: move; position: absolute;
							overflow: hidden; left: 0px; top: 0px; width: 800px; height: 600px;">';

		foreach ($msgpictures as $key => $value)
		{
			$file = $value['PictureFile'];
			$url = 'https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $file;
			echo '<div><img u="image" src="' . $url . '" /></div>';
		}

		echo '</div>';

		echo '<div u="navigator" class="jssorb01" style="bottom: 16px; right: 10px;">';
		echo '<div u="prototype"></div>';
		echo '</div>';

		echo '<span u="arrowleft" class="jssora02l" style="top: 123px; left: 8px;"></span>';
		echo '<span u="arrowright" class="jssora02r" style="top: 123px; right: 8px;"></span>';

		echo '</div></div>';
		echo $add_img;
		echo '</div>';

		echo '<div class="col-md-6">';
	}
	else
	{
		echo '<div class="col-md-12">';
		echo '<div id="slider1_container"></div>';
		$str = ($message['msg_type']) ? ' dit aanbod' : ' deze vraag';
		echo '<p>Er zijn geen afbeeldingen voor ' . $str . '.</p>';
		echo $add_img;
	}	

	echo '<div class="panel panel-default">';
	echo '<div class="panel-body">';

	if (!empty($message['Description']))
	{
		echo nl2br(htmlspecialchars($message['Description'],ENT_QUOTES));
	}
	else
	{
		echo '<i>Er werd geen omschrijving ingegeven.</i>';
	}

	echo '</div>';
	echo '</div>';

	echo '<dl class="dl-horizontal">';
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
	echo ' (saldo: <span class="label label-default">' . $balance . '</span> ' .$currency . ')';
	echo '</dd>';

	echo '<dt>Plaats</dt>';
	echo '<dd>' . $user['postcode'] . '</dd>';

	echo '<dt>Aangemaakt op</dt>';
	echo '<dd>' . $message['cdate'] . '</dd>';

	echo '<dt>Geldig tot</dt>';
	echo '<dd>' . $message['validity'] . '</dd>';

	echo '</dl>';

	echo '</div>'; //col-md-6
	echo '</div>'; //row

	echo '<div id="contacts" data-uid="' . $message['id_user'] . '"></div>';

	// response form
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<div class="col-sm-12">';
	echo '<textarea name="content" rows="6" placeholder="Je reactie naar ' . $user['name'] . '" ';
	echo 'class="form-control" required';
	if(empty($to) || $s_guest || $s_owner)
	{
		echo ' disabled';
	}
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

	echo '<input type="submit" name="zend" value="Versturen" class="btn btn-default"';
	if(empty($to) || $s_guest || $s_owner)
	{
		echo ' disabled';
	}
	echo '>';
	echo '</form>';

	echo '</div>';
	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

$s_owner = ($s_id == $uid && $s_id && $uid) ? true : false;

$sql_and_where = ($uid) ? ' and u.id = ? ' : '';
$sql_params = ($uid) ? array($uid) : array();

$msgs = $db->fetchAll('select m.*,
		u.postcode
	from messages m, users u
	where m.id_user = u.id
		and u.status in (1, 2)
		' . $sql_and_where . '
	order by id desc', $sql_params);

$offer_sum = $want_sum = 0;

$cats = $cats_hsh = array();

$cats_hsh_name = array(
	''	=> '-- Alle categorieën --',
);

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

$ow_str = ' . . . . . . . V%1$s A%2$s';

while ($row = $st->fetch())
{
	$cats[$row['id']] = $row;	
	$c_hsh = substr(md5($row['id'] . $row['fullname']), 0, 4);
	if ($row['id_parent'])
	{
		$id_parent = $row['id_parent'];
		$cats_hsh_name[$c_hsh] = '. . . . . ' . $row['name'];
		$offer = $row['stat_msgs_offers'];
		$want = $row['stat_msgs_wanted'];
		$cats_hsh_name[$c_hsh] .= sprintf($ow_str, $want, $offer);
		$offer_sum += $offer;
		$want_sum += $want;
		$cats[$row['id']]['hsh'] = $p_hsh . ' ' . $c_hsh;	
	}
	else
	{
		$cats_hsh_name[$p_hsh] .= ($p_hsh) ? sprintf($ow_str, $want_sum, $offer_sum) : ''; 
		$cats_hsh_name[$c_hsh] = $row['name'];
		$offer_sum = $want_sum = 0;
		$p_hsh = $c_hsh;
		$cats[$row['id']]['hsh'] = $c_hsh;
	}

	$cats_hsh[$row['id']] = $c_hsh;	
}
$cats_hsh_name[$p_hsh] .= ($p_hsh) ? sprintf($ow_str, $want_sum, $offer_sum) : '';

$cat_hsh = ($cat_hsh) ?: (($cats_hsh[$cid]) ?: '');

if ($s_admin || $s_user)
{
	if (!$inline)
	{
		$top_buttons .= '<a href="' . $rootpath . 'messages.php?add=1" class="btn btn-success"';
		$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
	}

	if ($uid)
	{
		if ($s_admin && !$s_owner)
		{
			$str = 'Vraag of aanbod voor ' . link_user($uid, null, false);
			$top_buttons .= '<a href="' . $rootpath . 'messages.php?add=1&uid=' . $uid . '" ';
			$top_buttons .= 'class="btn btn-success" ';
			$top_buttons .= 'title="' . $str . '">';
			$top_buttons .= '<i class="fa fa-plus"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> ' . $str . '</span></a>';
		}

		if (!$inline)
		{
			$top_buttons .= '<a href="' . $rootpath . 'messages.php" class="btn btn-default"';
			$top_buttons .= ' title="Lijst alle vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';
		}
	}
	else
	{
		$top_buttons .= '<a href="' . $rootpath . 'messages.php?uid=' . $s_id . '" class="btn btn-default"';
		$top_buttons .= ' title="Mijn vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn vraag en aanbod</span></a>';
	}
}

if ($s_admin)
{
	$top_right .= '<a href="#" class="csv">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv</a>';
}

$h1 = 'Vraag & Aanbod';
$h1 .= ($uid) ? ' van ' . link_user($uid) : '';
$h1 = (!$s_admin && $s_owner) ? 'Mijn vraag en aanbod' : $h1;

$fa = 'newspaper-o';

if (!$inline)
{
	$includejs = '<script src="' . $rootpath . 'js/combined_filter_msgs.js"></script>
		<script src="' . $rootpath . 'js/msgs_sum.js"></script>
		<script src="' . $rootpath . 'js/csv.js"></script>';

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
	echo '<select class="form-control" id="cat_hsh" value="' . $cat_hsh . '" name="cat_hsh">';
	render_select_options($cats_hsh_name, $cat_hsh);
	echo '</select>';
	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '<input type="hidden" value="" id="combined-filter">';
	echo '<input type="hidden" value="' . $hsh . '" name="hsh" id="hsh">';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	echo '<div class="pull-right hidden-xs">';
	echo 'Totaal: <span id="total"></span>';
	echo '</div>';

	echo '<ul class="nav nav-tabs" id="nav-tabs">';
	echo '<li class="active"><a href="#" class="bg-white" data-filter="">Alle</a></li>';
	echo '<li><a href="#" class="bg-white" data-filter="34a9">Geldig</a></li>';
	echo '<li><a href="#" class="bg-danger" data-filter="09e9">Vervallen</a></li>';
	echo '</ul>';	
}
else
{
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	echo '<h3><i class="fa fa-newspaper-o"></i> ' . $h1;
	echo '<span class="inline-buttons">' . $top_buttons . '</span>';
	echo '</h3>';
}

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable csv"';
echo ' data-filter="#combined-filter" data-filter-minimum="1" id="msgs">';
echo '<thead>';
echo '<tr>';
echo "<th>V/A</th>";
echo "<th>Wat</th>";
if (!$uid)
{
	echo '<th data-hide="phone, tablet">Wie</th>';
	echo '<th>Postcode</th>';
}
echo '<th data-hide="phone, tablet">Categorie</th>';
echo '<th data-hide="phone, tablet">Geldig tot</th>';

if ($s_admin)
{
	echo '<th data-hide="phone, tablet" data-sort-ignore="true">';
	echo '[Admin] Verlengen</th>';
}
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($msgs as $msg)
{
	$del = (strtotime($msg['validity']) < time()) ? true : false;

	echo '<tr';
	echo ($del) ? ' class="danger"' : '';
	echo '>';

	echo '<td ';
	echo ' data-value="' . (($del) ? '09e9' : '34a9') . ' ' . $cats[$msg['id_category']]['hsh'] . '">';
	echo ($msg['msg_type']) ? 'Aanbod' : 'Vraag';
	echo '</td>';

	echo '<td>';
	echo '<a href="' .$rootpath . 'messages.php?id=' . $msg['id']. '">';
	echo htmlspecialchars($msg['content'],ENT_QUOTES);
	echo '</a>';
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

	echo '<td>';
	echo '<a href="' . $rootpath . 'messages.php?cid=' . $msg['id_category'] . '">';
	echo htmlspecialchars($cats[$msg['id_category']]['fullname'], ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo $msg['validity'];
	echo '</td>';

	if ($s_admin)
	{
		echo '<td>';
		echo '<a href="' . $rootpath . 'messages.php?extend=' . $msg['id'] . '&validity=12" class="btn btn-default btn-xs">';
		echo '1 jaar</a>&nbsp;';
		echo '<a href="' . $rootpath . 'messages.php?extend=' . $msg['id'] . '&validity=60" class="btn btn-default btn-xs">';
		echo '5 jaar</a>';
		echo '</td>';
	}

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

if ($inline)
{
	echo '</div></div>';
}
else
{
	include $rootpath . 'includes/inc_footer.php';
}

function cancel($id = null)
{
	global $rootpath;

	header('Location: ' . $rootpath . 'messages.php' . (($id) ? '?id=' . $id : ''));
	exit;
}

