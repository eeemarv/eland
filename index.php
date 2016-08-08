<?php

$rootpath = './';
$page_access = 'guest';
require_once $rootpath . 'includes/inc_default.php';

if (isset($hosting_form))
{
	if (isset($_POST['zend']))
	{
		$mail = $_POST['mail'];
		$group_name = $_POST['group_name'];
		$message = $_POST['message'];
		$browser = $_SERVER['HTTP_USER_AGENT'];
		$token = $_POST['token'];

		if (!$redis->get('hosting_form_' . $token))
		{
			$errors[] = 'Het formulier is verlopen.';
		}

		if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
		{
			$errors[] = 'Geen geldig mail adres ingevuld.';
		}

		if (!$group_name)
		{
			$errors[] = 'De naam van de letsgroep is niet ingevuld.';
		}

		if (!$message)
		{
			$errors[] = 'Het bericht is leeg.';
		}

		$to = getenv('MAIL_HOSTER_ADDRESS');
		$from = getenv('MAIL_FROM_ADDRESS');

		if (!$to || !$from)
		{
			$errors[] = 'Interne fout.';
		}

		if (!count($errors))
		{
			$subject = 'Aanvraag hosting: ' . $group_name;
			$text = $message . "\r\n\r\n\r\n" . 'browser: ' . $browser . "\n" . 'token: ' . $token;

			$enc = getenv('SMTP_ENC') ?: 'tls';
			$transport = Swift_SmtpTransport::newInstance(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc)
				->setUsername(getenv('SMTP_USERNAME'))
				->setPassword(getenv('SMTP_PASSWORD'));

			$mailer = Swift_Mailer::newInstance($transport);

			$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));

			$msg = Swift_Message::newInstance()
				->setSubject($subject)
				->setBody($text)
				->setTo($to)
				->setFrom($from)
				->setReplyTo($mail);

			$mailer->send($msg);

			header('Location: ' . $rootpath . '?form_ok=1');
		}
	}

	echo '<!DOCTYPE html>';
	echo '<html>';
	echo '<head>';
	echo '<title>eLAND hosting aanvraag</title>';
	echo '<link type="text/css" rel="stylesheet" href="' . $cdn_bootstrap_css . '" media="screen">';
	echo '<link type="text/css" rel="stylesheet" href="' . $rootpath . 'gfx/base.css" media="screen">';
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '</head>';
	echo '<body>';

	echo '<div class="navbar navbar-default navbar-fixed-top">';
	echo '<div class="container-fluid">';

	echo '<div class="navbar-header">';

	echo '<a href="http://letsa.net" class="navbar-brand">eLAND</a>';

	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="container-fluid">';
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	if ($_GET['form_ok'])
	{
		echo '<br><div class="panel panel-success">';
		echo '<div class="panel-heading">';
		echo '<h1>Uw aanvraag is verstuurd.</h1>';
		echo '<p>Er wordt spoedig contact met u opgenomen.</p>';
		echo '</div></div>';
		echo '<a href="http://letsa.net" class="btn btn-default">Naar de eLAND-documentatie</a>';
		echo '<br><br><br>';
	}
	else
	{
		if (count($errors))
		{
			echo '<br>';
			echo '<div class="panel panel-danger">';
			echo '<div class="panel-heading">';
			echo '<h3>Er deed zich een fout voor.</h3>';
			foreach ($errors as $error)
			{
				echo '<p>' . $error . '</p>';
			}
			echo '</div></div>';
			echo '<br>';

		}

		$token = sha1(microtime());
		$key = 'hosting_form_' . $token;
		$redis->set($key, '1');
		$redis->expire($key, 7200);

		echo '<h1>Aanvraag hosting eLAND</h1>';

		echo '<div class="panel panel-default">';
		echo '<div class="panel-heading">';

		echo '<form method="post" class="form-horizontal">';

		echo '<div class="form-group">';
		echo '<label for="subject" class="col-sm-2 control-label">Naam letsgroep</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" name="group_name" ';
		echo 'value="' . $group_name . '" required>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="mail" class="col-sm-2 control-label">Email*</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="email" class="form-control" id="mail" name="mail" ';
		echo 'value="' . $mail . '" required>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="message" class="col-sm-2 control-label">Bericht</label>';
		echo '<div class="col-sm-10">';
		echo '<textarea name="message" class="form-control" id="message" rows="6" required>';
		echo $message;
		echo '</textarea>';
		echo '</div>';
		echo '</div>';

		echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
		echo '<input type="hidden" name="token" value="' . $token . '">';
		echo '</form>';

		echo '</div>';
		echo '</div>';

		echo '<br>';
		echo '<p>*Privacy: uw email adres wordt voor geen enkel ander doel gebruikt dan u terug te kunnen ';
		echo 'contacteren.</p>';

	}
	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '<br><br><br><br><br><br>';
	echo '<footer class="footer">';

	echo '<p><a href="http://letsa.net">eLAND';
	echo '</a>&nbsp; web app voor letsgroepen</p>';

	echo '</footer>';
	echo '</div>';
	echo '</body>';
	echo '</html>';
	exit;
}

/**
 *
 **/

$newusers = $db->fetchAll('select id, letscode, name
	from users
	where status = 1
		and adate > ?', array(date('Y-m-d H:i:s', $newusertreshold)));

$status_msgs = false;

if ($s_admin)
{
	$non_unique_mail = $db->fetchAll('select c.value, count(c.*)
		from contact c, type_contact tc, users u
		where c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'
			and c.id_user = u.id
			and u.status in (1, 2)
		group by value
		having count(*) > 1');

	if (count($non_unique_mail))
	{
		$st = $db->prepare('select id_user
			from contact c
			where c.value = ?');

		foreach ($non_unique_mail as $key => $ary)
		{
			$st->bindValue(1, $ary['value']);
			$st->execute();

			while ($row = $st->fetch())
			{
				$non_unique_mail[$key]['users'][$row['id_user']] = true;
			}
		}

		$status_msgs = true;
	}

//

	$non_unique_letscode = $db->fetchAll('select letscode, count(*)
		from users
		where letscode <> \'\'
		group by letscode
		having count(*) > 1');

	if (count($non_unique_letscode))
	{
		$st = $db->prepare('select id
			from users
			where letscode = ?');

		foreach ($non_unique_letscode as $key => $ary)
		{
			$st->bindValue(1, $ary['letscode']);
			$st->execute();

			while ($row = $st->fetch())
			{
				$non_unique_letscode[$key]['users'][$row['id']] = true;
			}
		}

		$status_msgs = true;
	}

//

	$non_unique_name = $db->fetchAll('select name, count(*)
		from users
		where name <> \'\'
		group by name
		having count(*) > 1');

	if (count($non_unique_name))
	{
		$st = $db->prepare('select id
			from users
			where name = ?');

		foreach ($non_unique_name as $key => $ary)
		{
			$st->bindValue(1, $ary['name']);
			$st->execute();

			while ($row = $st->fetch())
			{
				$non_unique_name[$key]['users'][$row['id']] = true;
			}
		}

		$status_msgs = true;
	}

//

	$unvalid_mail = $db->fetchAll('select c.id, c.value, c.id_user
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'
			and c.value !~ \'^[A-Za-z0-9!#$%&*+/=?^_`{|}~.-]+@[A-Za-z0-9.-]+[.][A-Za-z]+$\'');

//
	$no_mail = array();

	$st = $db->prepare(' select u.id
		from users u
		where u.status in (1, 2)
			and not exists (select c.id
				from contact c, type_contact tc
				where c.id_user = u.id
					and c.id_type_contact = tc.id
					and tc.abbrev = \'mail\')');

	$st->execute();

	while ($row = $st->fetch())
	{
		$no_mail[] = $row['id'];
		$status_msgs = true;
	}
//

	$empty_letscode = $db->fetchAll('select id
		from users
		where letscode = \'\'');

//
	$empty_name = $db->fetchAll('select id
		from users
		where name = \'\'');
//
	$version = $db->fetchColumn('select value from parameters where parameter = \'schemaversion\'');

	$db_update = ($version == $schemaversion) ? false : true;

//	$default_config = $db->fetchColumn('select setting from config where "default" = True');

	if ($unvalid_mail || $empty_letscode || $empty_name || $db_update || $default_config)
	{
		$status_msgs = true;
	}
}

$h1 = 'Overzicht';
$fa = 'home';

// $include_ary[] = 'index.js';

$app['eland.assets']->add('index.js');

include $rootpath . 'includes/inc_header.php';

if($s_admin)
{
	if ($status_msgs)
	{
		echo '<div class="panel panel-danger">';
		echo '<div class="panel-heading">';
		echo '<span class="label label-info">Admin</span> ';
		echo '<i class="fa fa-exclamation-triangle"></i> Status';
		echo '</div>';

		echo '<ul class="list-group">';

		if (count($non_unique_mail))
		{
			echo '<li class="list-group-item">';

			if (count($non_unique_mail) == 1)
			{
				echo 'Een mailadres komt meer dan eens voor onder de actieve gebruikers ';
				echo 'in de installatie. ';
				echo 'Gebruikers met dit mailadres kunnen niet inloggen met mailadres. ';
			}
			else
			{
				echo 'Meerdere mailadressen komen meer dan eens voor onder de actieve gebruikers in de installatie. ';
				echo 'Gebruikers met een mailadres dat meer dan eens voorkomt, kunnen niet inloggen met mailadres.';
			}

			echo '<ul>';

			foreach ($non_unique_mail as $ary)
			{
				echo '<li>';
				echo $ary['value'] . ' (' . $ary['count'] . '): ';

				$user_ary = array();

				foreach($ary['users'] as $user_id => $dummy)
				{
					$user_ary[] = link_user($user_id);
				}

				echo implode(', ', $user_ary);
				echo '</li>';
			}

			echo '</ul>';
			echo '</li>';
		}

		if (count($non_unique_letscode))
		{
			echo '<li class="list-group-item">';

			if (count($non_unique_letscode) == 1)
			{
				echo 'Een letscode komt meer dan eens voor in de installatie. ';
				echo 'Actieve gebruikers met deze letscode kunnen niet inloggen met letscode ';
				echo 'en kunnen geen transacties doen of transacties ontvangen. ';
			}
			else
			{
				echo 'Meerdere letscodes komen meer dan eens voor in de installatie. ';
				echo 'Gebruikers met een letscode die meer dan eens voorkomt, kunnen niet inloggen met letscode ';
				echo 'en kunnen geen transacties doen of transacties ontvangen.';
			}

			echo '<ul>';
			foreach ($non_unique_letscode as $ary)
			{
				echo '<li>';
				echo $ary['letscode'] . ' (' . $ary['count'] . '): ';

				$user_ary = array();

				foreach($ary['users'] as $user_id => $dummy)
				{
					$user_ary[] = link_user($user_id);
				}

				echo implode(', ', $user_ary);
				echo '</li>';
			}
			echo '</ul>';
			echo '</li>';
		}

		if (count($non_unique_name))
		{
			echo '<li class="list-group-item">';

			if (count($non_unique_name) == 1)
			{
				echo 'Een gebruikersnaam komt meer dan eens voor in de installatie. ';
				echo 'Actieve gebruikers met deze gebruikersnaam kunnen niet inloggen met gebruikersnaam. ';
			}
			else
			{
				echo 'Meerdere gebruikersnamen komen meer dan eens voor in de installatie. ';
				echo 'Actieve gebruikers met een gebruikersnaam die meer dan eens voorkomt, kunnen niet inloggen met gebruikersnaam.';
			}

			echo '<ul>';
			foreach ($non_unique_name as $ary)
			{
				echo '<li>';
				echo $ary['name'] . ' (' . $ary['count'] . '): ';

				$user_ary = array();

				foreach($ary['users'] as $user_id => $dummy)
				{
					$user_ary[] = link_user($user_id);
				}

				echo implode(', ', $user_ary);
				echo '</li>';
			}
			echo '</ul>';
			echo '</li>';
		}

		if (count($unvalid_mail))
		{
			echo '<li class="list-group-item">';
			if (count($unvalid_mail) == 1)
			{
				echo 'Deze installatie bevat een fout geformateerd email adres. Pas het aan of verwijder het!';
			}
			else
			{
				echo 'Deze installatie bevat fout geformateerde emails. Verwijder deze of pas deze aan!';
			}

			echo '<ul>';
			foreach ($unvalid_mail as $ary)
			{
				echo '<li>';
				echo $ary['value'] .  ' ';
				echo aphp('contacts', ['edit' => $ary['id']], 'Aanpassen', 'btn btn-default btn-xs') . ' ';
				echo aphp('contacts', ['del' => $ary['id']], 'Verwijderen', 'btn btn-danger btn-xs') . ' ';
				echo ' : ' . link_user($ary['id_user']);
				echo '</li>';
			}

			echo '</ul>';
			echo '</li>';
		}

		if (count($no_mail))
		{
			echo '<li class="list-group-item">';
			if (count($no_mail) == 1)
			{
				echo 'Eén actieve gebruiker heeft geen emailadres.';
			}
			else
			{
				echo count($no_mail) . ' actieve gebruikers hebben geen mailadres.';
			}

			echo '<ul>';
			foreach ($no_mail as $user_id)
			{
				echo '<li>';
				echo link_user($user_id);
				echo '</li>';
			}

			echo '</ul>';
			echo '</li>';
		}

		if (count($empty_name))
		{
			echo '<li class="list-group-item">';
			if (count($empty_name) == 1)
			{
				echo 'Eén gebruiker heeft geen gebruikersnaam.';
			}
			else
			{
				echo count($empty_name) . ' gebruikers hebben geen gebruikersnaam.';
			}

			echo '<ul>';
			foreach ($empty_name as $ary)
			{
				echo '<li>';
				echo link_user($ary['id']);
				echo '</li>';
			}

			echo '</ul>';
			echo '</li>';
		}

		if (count($empty_letscode))
		{
			echo '<li class="list-group-item">';
			if (count($empty_letscode) == 1)
			{
				echo 'Eén gebruiker heeft geen letscode.';
			}
			else
			{
				echo count($empty_letscode) . ' gebruikers hebben geen letscode.';
			}

			echo '<ul>';
			foreach ($empty_letscode as $ary)
			{
				echo '<li>';
				echo link_user($ary['id']);
				echo '</li>';
			}

			echo '</ul>';
			echo '</li>';
		}

		if ($db_update)
		{
			echo '<li class="list-group-item">';
			echo 'Een database update is nodig.';
			echo '</li>';
		}

/*
		if ($default_config)
		{
			echo '<li class="list-group-item">';
			echo 'Er zijn nog settings met standaardwaarden, ';
			echo 'Kijk in de ' . aphp('config', [], 'instellingen') . ' ';
			echo 'om ze te wijzigen of bevestigen';
			echo '</li>';
		}
*/

		echo '</ul>';
		echo '</div>';
	}
}

echo '<div id="news" ';
echo 'data-url="' . $rootpath . 'news.php?inline=1';
echo '&' . http_build_query(get_session_query_param()) . '" class="printview"></div>';

if($newusers)
{
	echo '<h3 class="printview">';
	echo aphp('users', ['status' => 'new'], 'Nieuwe leden', false, false, 'users');
	echo '</h3>';

	echo '<div class="panel panel-success printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-bordered table-striped table-hover">';

	echo '<tbody>';

	foreach($newusers as $u)
	{
		$id = $u['id'];

		echo '<tr class="success">';

		echo '<td>' . link_user($id) . '</td>';

		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table>';
	echo '</div>';
	echo '</div>';
}

echo '<div id="messages" ';
echo 'data-url="' . $rootpath . 'messages.php?inline=1&recent=1&limit=10';
echo '&' . http_build_query(get_session_query_param()) . '" class="printview"></div>';

include $rootpath . 'includes/inc_footer.php';
