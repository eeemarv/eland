<?php
ob_start();
$rootpath = './';

$id = ($_GET['id']) ?: false;
$del = ($_GET['del']) ?: false;
$edit = ($_GET['edit']) ?: false;
$add = ($_GET['add']) ?: false;
$password = ($_POST['password']) ?: false;
$submit = ($_POST['zend']) ? true : false;

$q = ($_GET['q']) ?: '';
$hsh = ($_GET['hsh']) ?: '';

if ($del || $add)
{
	$role = 'admin';
}
else if ($edit)
{
	$role = 'user';
}
else
{
	$role = 'guest';
}

require_once $rootpath . 'includes/inc_default.php';

if ($del)
{
	if ($s_id == $del)
	{
		$alert->error('Je kan jezelf niet verwijderen.');
		cancel();
	}

	if ($db->fetchColumn('select id from transactions where id_to = ? or id_from = ?', array($del, $del)))
	{
		$alert->error('Een gebruiker met transacties kan niet worden verwijderd.');
		cancel();
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
				$s3 = Aws\S3\S3Client::factory(array(
					'signature'	=> 'v4',
					'region'	=> 'eu-central-1',
					'version'	=> '2006-03-01',
				));

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
					$result = $s3->deleteObject(array(
						'Bucket' => getenv('S3_BUCKET'),
						'Key'    => $row['PictureFile'],
					));

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
				if (isset($user['PictureFile']))
				{
					$result = $s3->deleteObject(array(
						'Bucket' => getenv('S3_BUCKET'),
						'Key'    => $user['PictureFile'],
					));
				}

				//finally, the user
				$db->delete('users', array('id' => $del));
				$redis->expire($schema . '_user_' . $del, 0);

				$alert->success('De gebruiker is verwijderd.');
				cancel();
			}
			else
			{
				$alert->error('Paswoord is niet correct.');
			}
		}
		else
		{
			$alert->error('Paswoord is niet ingevuld.');
		}
	}

	$h1 = 'Gebruiker ' . $user['letscode'] . ' ' . $user['name'] . ' verwijderen?';
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

	echo '<a href="' . $rootpath . 'users.php?id=' . $id . '" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 *
 */

if ($add || $edit)
{
	if ($edit && $s_user && $s_id != $edit)
	{
		$alert->error('Je hebt geen rechten om deze gebruiker aan te passen.');
		cancel($edit);
	}

	if ($submit)
	{
		
	}

	$includejs = '
		<script src="' . $cdn_datepicker . '"></script>
		<script src="' . $cdn_datepicker_nl . '"></script>
		<script src="' . $rootpath . 'js/generate_password.js"></script>
		<script src="' . $rootpath . 'js/generate_password_onload.js"></script>';

	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

	$h1 = 'Gebruiker ' . (($id) ? 'aanpassen' : 'toevoegen');
	$fa = 'user';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="name" name="name" ';
	echo 'value="' . $user['name'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="fullname" class="col-sm-2 control-label">Volledige naam (Voornaam en Achternaam)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="fullname" name="fullname" ';
	echo 'value="' . $user['fullname'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="letscode" class="col-sm-2 control-label">Letscode</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="letscode" name="letscode" ';
	echo 'value="' . $user['letscode'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="postcode" class="col-sm-2 control-label">Postcode</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="postcode" name="postcode" ';
	echo 'value="' . $user['postcode'] . '">';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="birthday" class="col-sm-2 control-label">Geboortedatum (jjjj-mm-dd)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="birthday" name="birthday" ';
	echo 'value="' . $user['birthday'] . '" required ';
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
	echo '<label for="login" class="col-sm-2 control-label">Login</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="login" name="login" ';
	echo 'value="' . $user['login'] . '">';
	echo '</div>';
	echo '</div>';

	$role_ary = array(
		'admin'		=> 'Admin',
		'user'		=> 'User',
		'guest'		=> 'Guest',
		'interlets'	=> 'Interlets',
	);

	echo '<div class="form-group">';
	echo '<label for="accountrole" class="col-sm-2 control-label">Rechten</label>';
	echo '<div class="col-sm-10">';
	echo '<select id="accountrole" name="accountrole" class="form-control">';
	render_select_options($role_ary, $user['accountrole']);
	echo '</select>';
	echo '</div>';
	echo '</div>';

	$status_ary = array(
		0	=> 'Inactief',
		1	=> 'Actief',
		2	=> 'Uitstapper',	
		5	=> 'Info-pakket',
		6	=> 'Info-moment',
		7	=> 'Extern',
	);

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

	echo '<div class="form-group">';
	echo '<label for="cron_saldo" class="col-sm-2 control-label">Periodieke saldo mail met recent vraag en aanbod</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="checkbox" name="cron_saldo" id="cron_saldo"';
	echo ($user['cron_saldo'] == 't') ? ' checked="checked"' : '';
	echo '>';
	echo '</div>';
	echo '</div>';

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
		echo '<label for="' . $public . '" class="col-sm-2 control-label">Zichtbaar</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="checkbox" id="' . $public . '" name="' . $public . '" ';
		echo 'value="1"';
		echo  ($c['flag_public']) ? ' checked="checked"' : '';
		echo '>';
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

	if (!$id)
	{
		echo '<button class="btn btn-default" id="generate">Genereer automatisch ander paswoord</button>';
		echo '<br><br>';
		
		echo '<div class="form-group">';
		echo '<label for="pw" class="col-sm-2 control-label">Paswoord</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" id="pw" name="pw" ';
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

	$cancel_red = ($id) ? 'view.php?id=' . $id : 'overview.php';
	$btn = ($id) ? 'primary' : 'success';
	echo '<a href="' . $rootpath . 'users/' . $cancel_red . '" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/*
 *
 */

if ($id)
{
	$s_owner = ($s_id == $id) ? true : false;

	$user = readuser($id);

	$contacts = $db->fetchAll('select c.*, tc.abbrev
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?', array($id));

	$messages = $db->fetchAll('SELECT *
		FROM messages
		where id_user = ?
			and validity > now()
		order by cdate', array($id));

	$transactions = $db->fetchAll('select t.*,
			fu.name as from_username,
			tu.name as to_username,
			fu.letscode as from_letscode,
			tu.letscode as to_letscode
		from transactions t, users fu, users tu
		where (t.id_to = ?
			or t.id_from = ?)
			and t.id_to = tu.id
			and t.id_from = fu.id', array($id, $id));

	$currency = readconfigfromdb('currency');

	$includejs = '<script type="text/javascript">var user_id = ' . $id . ';
		var user_link_location = \'' . $rootpath . 'users.php?id=\'; </script>
		<script src="' . $rootpath . 'js/user.js"></script>
		<script src="' . $cdn_jqplot . 'jquery.jqplot.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.donutRenderer.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.cursor.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.dateAxisRenderer.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasTextRenderer.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
		<script src="' . $cdn_jqplot . 'plugins/jqplot.highlighter.min.js"></script>
		<script src="' . $rootpath . 'js/plot_user_transactions.js"></script>';

	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_jqplot . 'jquery.jqplot.min.css" />';

	$top_buttons = '';

	if ($s_admin)
	{
		$top_buttons .= '<a href="' . $rootpath . 'users.php?add=1" class="btn btn-success"';
		$top_buttons .= ' title="gebruiker toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
	}

	if ($s_admin || $s_id == $id)
	{
		$top_buttons .= '<a href="' . $rootpath . 'users.php?edit=' . $id . '" class="btn btn-primary"';
		$top_buttons .= ' title="Gebruiker aanpassen"><i class="fa fa-pencil"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

		$top_buttons .= '<a href="editpw.php?id='. $id . '" class="btn btn-info"';
		$top_buttons .= ' title="Paswoord aanpassen"><i class="fa fa-key"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Paswoord aanpassen</span></a>';
	}

	if ($s_admin && !count($transactions) && $s_id != $id)
	{
		$top_buttons .= '<a href="delete.php?id=' . $id . '" class="btn btn-danger"';
		$top_buttons .= ' title="gebruiker verwijderen">';
		$top_buttons .= '<i class="fa fa-times"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';
	}

	$top_buttons .= '<a href="' . $rootpath . 'users.php" class="btn btn-default"';
	$top_buttons .= ' title="Lijst"><i class="fa fa-users"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

	$h1 = $user['letscode'] . ' ' . $user['name'];
	$fa = 'user';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="row">';
	echo '<div class="col-md-4">';

	if(isset($user['PictureFile']))
	{
		echo '<img class="img-rounded" src="https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $user['PictureFile'] . '" width="250"></img>';
	}
	else
	{
		echo '<i class="fa fa-user fa-5x text-muted"></i><br>Geen profielfoto';
	}

	echo '</div>';
	echo '<div class="col-md-8">';

	echo '<dl>';
	echo '<dt>';
	echo 'Naam';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['name'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Volledige naam';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['fullname'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Postcode';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['postcode'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Geboortedatum';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['birthday'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Hobbies / Interesses';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['hobbies'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Commentaar';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['comments'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Login';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['login'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Tijdstip aanmaak';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['cdate'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Tijdstip activering';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['adate'],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Laatste login';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user['lastlogin'],ENT_QUOTES);
	echo '</dd>';

	$status_ary = array(
		0	=> 'Gedesactiveerd',
		1	=> 'Actief',
		2	=> 'Uitstapper',
		3	=> 'Instapper', // not used
		4	=> 'Infopakket',
		5	=> 'Infoavond',
		6	=> 'Extern',
	);

	echo '<dt>';
	echo 'Rechten';
	echo '</dt>';
	echo '<dd>';
	echo $status_ary[$user['status']];
	echo '</dd>';

	echo '<dt>';
	echo 'Commentaar van de admin';
	echo '</dt>';
	echo '<dd>';
	echo htmlspecialchars($user["admincomment"],ENT_QUOTES);
	echo '</dd>';

	echo '<dt>';
	echo 'Saldo, limiet min, limiet max';
	echo '</dt>';
	echo '<dd>';
	echo '<span class="label label-default">' . $user['saldo'] . '</span>&nbsp;';
	echo '<span class="label label-danger">' . $user['minlimit'] . '</span>&nbsp;';
	echo '<span class="label label-success">' . $user['maxlimit'] . '</span>';
	echo '</dd>';

	echo '<dt>';
	echo 'Periodieke Saldo mail met recent vraag en aanbod';
	echo '</dt>';
	echo '<dd>';
	echo ($user["cron_saldo"] == 't') ? 'Aan' : 'Uit';
	echo '</dd>';
	echo '</dl>';

	echo '</div></div>';

	echo '<div id="contacts" data-uid="' . $id . '"></div>';

	echo '<div class="row">';
	echo '<div class="col-md-12">';
	echo '<h3>Saldo: <span class="label label-default">' . $user['saldo'] . '</span> ';
	echo $currency . '</h3>';
	echo '</div></div>';

	echo '<div class="row">';
	echo '<div class="col-md-6">';
	echo '<div id="chartdiv1" data-height="480px" data-width="960px"></div>';
	echo '</div>';
	echo '<div class="col-md-6">';
	echo '<div id="chartdiv2" data-height="480px" data-width="960px"></div>';
	echo '<h4>Interacties laatste jaar</h4>';
	echo '</div>';
	echo '</div>';

	echo '<div class="row">';
	echo '<div class="col-md-12">';
	echo '<h3><i class="fa fa-newspaper-o"></i> Vraag en aanbod ';
	echo '<a href="' . $rootpath . 'messages/edit.php?mode=new&uid=' . $id . '"';
	echo ' class="btn btn-success" title="Vraag of aanbod toevoegen">';
	echo '<i class="fa fa-plus"></i><span class="hidden-xs"> Toevoegen</span></a>';
	echo '</h3>';

	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable">';

	echo '<thead>';
	echo '<tr>';
	echo '<th>V/A</th>';
	echo '<th>Wat</th>';
	echo '<th data-hide="phone, tablet">Geldig tot</th>';
	echo '<th data-hide="phone, tablet">Geplaatst</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach ($messages as $m)
	{
		$class = (strtotime($m['validity']) < time()) ? ' class="danger"' : '';
		list($validity) = explode(' ', $m['validity']);
		list($cdate) = explode(' ', $m['cdate']);
		
		echo '<tr' . $class . '>';
		echo '<td>';
		echo ($m['msg_type']) ? 'Aanbod' : 'Vraag';
		echo '</td>';
		echo '<td>';
		echo '<a href="' . $rootpath . 'messages/view.php?id=' . $m['id'] . '">';
		echo htmlspecialchars($m['content'],ENT_QUOTES);
		echo '</a>';
		echo '</td>';
		echo '<td>';
		echo $validity;
		echo '</td>';
		echo '<td>';
		echo $cdate;
		echo '</td>';
		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table>';

	echo '</div>';
	echo '</div></div>';

	echo '<div class="row">';
	echo '<div class="col-md-12">';

	echo '<h3><i class="fa fa-exchange"></i> Transacties ';
	echo '<a href="' . $rootpath . 'transactions/add.php?uid=' . $id . '"';
	echo ' class="btn btn-success" title="Transactie naar ' . $user['fullname'] . '">';
	echo '<i class="fa fa-plus"></i><span class="hidden-xs"> Transactie naar</span></a> ';
	echo '<a href="' . $rootpath . 'transactions/add.php?fuid=' . $id . '"';
	echo ' class="btn btn-success" title="Transactie van ' . $user['fullname'] . '">';
	echo '<i class="fa fa-plus"></i><span class="hidden-xs"> Transactie van</span></a> ';
	echo '<a href="' . $rootpath . 'print_usertransacties.php?id=' . $id . '"';
	echo ' class="btn btn-default" title="Print transactielijst">';
	echo '<i class="fa fa-print"></i><span class="hidden-xs"> Print transactielijst</span></a> ';
	echo '<a href="' . $rootpath . 'export/export_transactions.php?userid=' . $id . '"';
	echo ' class="btn btn-default" title="csv export transacties">';
	echo '<i class="fa fa-file"></i><span class="hidden-xs"> Export csv</span></a>';
	echo '</h3>';

	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable">';

	echo '<thead>';
	echo '<tr>';
	echo '<th>Omschrijving</th>';
	echo '<th>Bedrag</th>';
	echo '<th data-hide="phone" data-sort-initial="descending">Tijdstip</th>';
	echo '<th data-hide="phone, tablet">Uit/In</th>';
	echo '<th data-hide="phone, tablet">Tegenpartij</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($transactions as $t){

		echo '<tr>';
		echo '<td>';
		echo '<a href="' . $rootpath . 'transactions/view.php?id=' . $t['id'] . '">';
		echo htmlspecialchars($t['description'], ENT_QUOTES);
		echo '</a>';
		echo '</td>';
		
		echo '<td>';
		echo '<span class="text-';
		echo ($t['id_from'] == $id) ? 'danger">-' : 'success">';
		echo $t['amount'];
		echo '</span></td>';

		echo '<td>';
		echo $t['cdate'];
		echo '</td>';

		echo '<td>';
		echo ($t['id_from'] == $id) ? 'Uit' : 'In'; 
		echo '</td>';

		if ($t['id_from'] == $id)
		{
			if ($t['real_to'])
			{
				$other_user = htmlspecialchars($t['real_to'], ENT_QUOTES);
			}
			else
			{
				$other_user = '<a href="' . $rootpath . 'users/view.php?id=' . $t['id_to'] . '">';
				$other_user .= htmlspecialchars($t['to_letscode'] . ' ' . $t['to_username'], ENT_QUOTES);
				$other_user .= '</a>';
			}
		}
		else
		{
			if ($t['real_from'])
			{
				$other_user = htmlspecialchars($t['real_from'], ENT_QUOTES);
			}
			else
			{
				$other_user = '<a href="' . $rootpath . 'users/view.php?id=' . $t['id_from'] . '">';
				$other_user .= htmlspecialchars($t['from_letscode'] . ' ' . $t['from_username'], ENT_QUOTES);
				$other_user .= '</a>';
			}
		}

		echo '<td>';
		echo $other_user;
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';

	echo '</div>';
	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;

}

/*
 *
 */

$st = array(
	'active'	=> array(
		'lbl'	=> ($s_admin) ? 'Actief' : 'Alle',
		'st'	=> 1,
		'hsh'	=> '58d267',
	),
	'leaving'	=> array(
		'lbl'	=> 'Uitstappers',
		'st'	=> 2,
		'hsh'	=> 'ea4d04',
		'cl'	=> 'danger',
	),
	'new'		=> array(
		'lbl'	=> 'Instappers',
		'st'	=> 3,
		'hsh'	=> 'e25b92',
		'cl'	=> 'success',
	),
);

if ($s_admin)
{
	$st = array(
		'all'		=> array(
			'lbl'	=> 'Alle',
		)
	) + $st + array(
		'inactive'	=> array(
			'lbl'	=> 'Inactief',
			'st'	=> 0,
			'hsh'	=> '79a240',
			'cl'	=> 'inactive',
		),
		'info-packet'	=> array(
			'lbl'	=> 'Info-pakket',
			'st'	=> 5,
			'hsh'	=> '2ed157',
			'cl'	=> 'warning',
		),
		'info-moment'	=> array(
			'lbl'	=> 'Info-moment',
			'st'	=> 6,
			'hsh'	=> '065878',
			'cl'	=> 'info',
		),
		'extern'	=> array(
			'lbl'	=> 'Extern',
			'st'	=> 7,
			'hsh'	=> '05306b',
			'cl'	=> 'extern',
		),
	);
}

$status_ary = array(
	0 	=> 'inactive',
	1 	=> 'active',
	2 	=> 'leaving',
	3	=> 'new',
	5	=> 'info-packet',
	6	=> 'info-moment',
	7	=> 'extern',
);

$currency = readconfigfromdb('currency');

$where = ($s_admin) ? '' : 'where u.status in (1, 2)';

$users = $db->fetchAll('select u.*
	from users u
	' . $where . '
	order by u.letscode asc');

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

$c_ary = $db->fetchAll('SELECT tc.abbrev, c.id_user, c.value, c.flag_public
	FROM contact c, type_contact tc
	WHERE tc.id = c.id_type_contact
		AND tc.abbrev IN (\'mail\', \'tel\', \'gsm\')');

$contacts = array();

foreach ($c_ary as $c)
{
	$contacts[$c['id_user']][$c['abbrev']][] = array($c['value'], $c['flag_public']);
}

if ($s_admin)
{
	$top_buttons = '<a href="' . $rootpath . 'users.php?add=1" class="btn btn-success"';
	$top_buttons .= ' title="Gebruiker toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

	$top_buttons .= '<a href="' . $rootpath . 'users/saldomail.php" class="btn btn-default"';
	$top_buttons .= ' title="Saldo mail aan/uitzetten"><i class="fa fa-envelope-o"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Saldo mail</span></a>';

	$h1 = 'Gebruikers';
}
else
{
	$h1 = 'Leden';
}

$fa = 'users';

$includejs = '<script src="' . $rootpath . 'js/combined_filter.js"></script>
	<script src="' . $rootpath . 'js/calc_sum.js"></script>';

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

echo '<input type="hidden" value="" id="combined-filter">';
echo '<input type="hidden" value="' . $hsh . '" name="hsh" id="hsh">';
echo '</form>';

echo '</div>';
echo '</div>';

echo '<div class="pull-right hidden-xs">';
echo 'Totaal: <span id="total"></span>';
echo '</div>';

echo '<ul class="nav nav-tabs" id="nav-tabs">';

$default_tab = ($s_admin) ? 'all' : 'active';

foreach ($st as $k => $s)
{
	$class_li = ($k == $default_tab) ? ' class="active"' : '';
	$class_a  = ($s['cl']) ?: 'white';
	echo '<li' . $class_li . '><a href="#" class="bg-' . $class_a . '" ';
	echo 'data-filter="' . (($s['hsh']) ?: '') . '">' . $s['lbl'] . '</a></li>';
}

echo '</ul>';
echo '<input type="hidden" value="" id="combined-filter">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable"';
echo ' data-filter="#combined-filter" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th>Naam</th>';
echo '<th data-hide="phone, tablet">Rol</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Tel</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">gsm</th>';
echo '<th data-hide="phone">Postc</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Mail</th>';
echo '<th data-hide="phone">Saldo</th>';

if ($s_admin)
{
	echo '<th data-hide="all">Min</th>';
	echo '<th data-hide="all">Max</th>';
	echo '<th data-hide="all">Ingeschreven</th>';
	echo '<th data-hide="all">Geactiveerd</th>';
	echo '<th data-hide="all">Laatst aangepast</th>';
	echo '<th data-hide="all">Laatst ingelogd</th>';
	echo '<th data-hide="all">Profielfoto</th>';
	echo '<th data-hide="all">Admin commentaar</th>';
	echo '<th data-hide="all" data-sort-ignore="true">Aanpassen</th>';
}

echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($users as $u)
{
	$id = $u['id'];

	$status_key = $status_ary[$u['status']];
	$status_key = ($status_key == 'active' && $newusertreshold < strtotime($u['adate'])) ? 'new' : $status_key;

	$hsh = ($st[$status_key]['hsh']) ?: '';
	$hsh .= ($status_key == 'leaving' || $status_key == 'new') ? $st['active']['hsh'] : '';

	$class = ($st[$status_key]['cl']) ? ' class="' . $st[$status_key]['cl'] . '"' : '';

	echo '<tr' . $class . ' data-balance="' . $u['saldo'] . '">';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users.php?id=' .$id .'">';
	echo $u['letscode'];
	echo '</a></td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users.php?id=' .$id .'">'.htmlspecialchars($u['name'],ENT_QUOTES);
	echo '</a></td>';

	echo '<td>';
	echo $u['accountrole'];
	echo '</td>';

	echo '<td data-value="' . $hsh . '">';
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
		echo '<span class="label label-danger">' . $u['minlimit'] . '</span>';
		echo '</td>';

		echo '<td>';
		echo '<span class="label label-success">' . $u['maxlimit'] . '</span>';
		echo '</td>';

		echo '<td>';
		echo $u['cdate'];
		echo '</td>';

		echo '<td>';
		echo $u['adate'];
		echo '</td>';

		echo '<td>';
		echo $u['mdate'];
		echo '</td>';

		echo '<td>';
		echo $u['lastlogin'];
		echo '</td>';
		
		echo '<td>';
		echo ($u['PictureFile']) ? 'Ja' : 'Nee';
		echo '</td>';

		echo '<td>';
		echo htmlspecialchars($u['admincomment'], ENT_QUOTES);
		echo '</td>';

		echo '<td>';
		echo '<a href="' . $rootpath . 'users.php?edit=' . $id . '" ';
		echo 'class="btn btn-primary btn-xs"><i class="fa fa-pencil"></i> Aanpassen</a>';
		echo '</td>';
	}

	echo '</tr>';

}
echo '</tbody>';
echo '</table>';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading">';
echo '<p>Totaal saldo van geselecteerde gebruikers: <span id="sum"></span> ' . $currency . '</p>';
echo '</div></div>';
echo '</div>';
echo '</div>';
echo '</div>';

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
			}
			else
			{
				echo '<span class="label label-default">privé</span>';
			}

			if ($key == $end)
			{
				break;
			}
			echo '<br>';
		}
	}
	else
	{
		echo '&nbsp;';
	}
}

function cancel($id = null)
{
	global $rootpath;

	header('Location: ' . $rootpath . 'users.php' . (($id) ? '?id=' . $id : ''));
	exit;
}
