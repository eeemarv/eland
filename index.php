<?php

$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

if ($hosting_form)
{
	if (isset($_POST['zend']))
	{
		$mail = $_POST['mail'];
		$letsgroup_name = $_POST['letsgroup_name'];
		$message = $_POST['message'];
		$browser = $_SERVER['HTTP_USER_AGENT'];
		$token = $_POST['token'];

		$errors = array();

		if (!$redis->get('hosting_form_' . $token))
		{
			$errors[] = 'Het formulier is verlopen.';
		}

		if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
		{
			$errors[] = 'Geen geldig mail adres ingevuld.';
		}

		if (!$letsgroup_name)
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
			$subject = 'Aanvraag hosting: ' . $letsgroup_name;
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
		echo '<input type="text" class="form-control" name="letsgroup_name" ';
		echo 'value="' . $letsgroup_name . '" required>';
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

		echo '<p>*Privacy: uw email adres wordt voor geen enkel ander doel gebruikt dan u terug te kunnen ';
		echo 'contacteren.</p>';
	}
	echo '</div>';
	echo '</div>';

	echo '</div>';

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

$news_where = ($s_admin) ? '' : ' where approved = True ';
$news = $db->fetchAll('select *, to_char(itemdate, \'YYYY-MM-DD\') as idate
	from news ' . $news_where . ' order by itemdate desc');

$newusers = $db->fetchAll('select id, letscode, name
	from users
	where status = 1
		and adate > ?', array(date('Y-m-d H:i:s', $newusertreshold)));

$sql_local = ($s_guest) ? ' and m.local = \'f\' ' : '';

$msgs = $db->fetchAll('SELECT m.*,
		to_char(m.validity, \'YYYY-MM-DD\') as validity_short,
		u.postcode,
		c.fullname as cat,
		c.id as cid
	from messages m, users u, categories c
	where m.id_user = u.id
		and u.status in (1, 2)
		and m.id_category = c.id
		' . $sql_local . '
	order by m.cdate DESC
	limit 100');

if ($s_admin)
{
	$dup_letscode = $db->fetchColumn('select u1.letscode
		from users u1, users u2
		where u1.letscode = u2.letscode
			and u1.id <> u2.id');

	$dup_mail = $db->fetchColumn('select c1.value
		from contact c1, contact c2, type_contact tc
		where c1.id_type_contact = tc.id
			and c2.id_type_contact = tc.id
			and tc.abbrev = \'mail\'
			and c1.id <> c2.id
			and c1.value = c2.value');

	$dup_name = $db->fetchColumn('select u1.name
		from users u1, users u2
		where u1.name = u2.name
			and u1.id <> u2.id');

	$emp_letscode = $db->fetchColumn('select id
		from users
		where letscode = \'\'');

	$emp_name = $db->fetchColumn('select id
		from users
		where letscode = \'\'');

	$emp_mail = $db->fetchColumn('select c.id_user
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'
			and c.value = \'\'');

	$version = $db->fetchColumn('select value from parameters where parameter = \'schemaversion\'');
	$db_update = ($version == $schemaversion) ? false : true;
	$default_config = $db->fetchColumn('select setting from config where "default" = True');
}

$h1 = 'Overzicht';
$fa = 'home';

include $rootpath . 'includes/inc_header.php';

if($s_admin)
{
	if ($db_update || $default_config || $dup_letscode || $dup_name || $dup_mail
		|| $emp_mail || $emp_name || $emp_letscode)
	{
		echo '<div class="panel panel-danger">';
		echo '<div class="panel-heading">';
		echo '<span class="label label-info">Admin</span> ';
		echo '<i class="fa fa-exclamation-triangle"></i> Status';
		echo '</div>';

		echo '<ul class="list-group">';
		if ($db_update)
		{
			echo '<li class="list-group-item">';
			echo 'Een database update is nodig.';
			echo '</li>';
		}
		if ($default_config)
		{
			echo '<li class="list-group-item">';
			echo 'Er zijn nog settings met standaardwaarden, ';
			echo 'Kijk in de ' . aphp('config', '', 'instellingen') . ' ';
			echo 'om ze te wijzigen of bevestigen';
			echo '</li>';
		}
		if ($dup_mail)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicaat mail adres onder de gebruikers: ' . $dup_mail;
			echo '</li>';
		}
		if ($dup_letscode)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicate letscode onder de gebruikers: ' . $dup_letscode;
			echo '</li>';
		}
		if ($dup_name)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicate gebruikersnaam onder de gebruikers: ' . $dup_name;
			echo '</li>';
		}
		if ($emp_mail)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicaat mailadres onder de gebruikers: ' . link_user($emp_mail);
			echo '</li>';
		}
		if ($emp_letscode)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicate letscode onder de gebruikers: ' . link_user($emp_letscode);
			echo '</li>';
		}
		if ($emp_name)
		{
			echo '<li class="list-group-item">';
			echo 'Er is een duplicate gebruikersnaam onder de gebruikers: ' . link_user($emp_name);
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}
}

if($s_guest)
{
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo 'Welkom bij ' . $systemname;
	echo '</div>';
	echo '<div class="panel-body">';
	echo '<p>Je bent ingelogd als LETS-gast, je kan informatie ';
	echo 'raadplegen maar niets wijzigen. Transacties moet je ingeven in de installatie van je eigen groep.</p>';
	echo '<p>Waardering bij ' . $systemname . ' gebeurt met \'' . $currency . '\'. ';
	echo  readconfigfromdb('currencyratio') . ' ' . $currency;
	echo ' stemt overeen met 1 LETS uur.</p>';
	echo '</div>';
	echo '</div>';
}

if($news)
{
	echo '<h3 class="printview">';
	echo aphp('news', '', 'Nieuws', false, false, 'calendar');
	echo '</h3>';

	echo '<div class="panel panel-warning printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-striped table-hover table-bordered">';

	echo '<tbody>';
	foreach ($news as $value)
	{
		echo '<tr>';

		echo '<td>';
		echo aphp('news', 'id=' . $value['id'], $value['headline']);
		echo '</td>';

		echo '<td>';
		echo $value['idate'];
		echo '</td>';

		if ($s_admin)
		{
			echo '<td>';
			echo ($value['approved'] == 't') ? 'Ja' : 'Nee';
			echo '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table></div>';
	echo '</div>';
}

if($newusers)
{
	echo '<h3 class="printview">';
	echo aphp('users', 'status=new', 'Nieuwe leden', false, false, 'users');
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

if($msgs)
{
	echo '<h3 class="printview">';
	echo aphp('messages', '', 'Recent vraag en aanbod', false, false, 'newspaper-o');
	echo '</h3>';

	echo '<div class="panel panel-info printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable">';
	echo '<thead>';
	echo '<tr>';
	echo '<th>V/A</th>';
	echo '<th>Wat</th>';
	echo '<th data-hide="phone, tablet">Geldig tot</th>';
	echo '<th data-hide="phone, tablet">Wie</th>';
	echo '<th data-hide="phone, tablet">Categorie</th>';
	echo '<th data-hide="phone">Plaats</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach($msgs as $msg)
	{
		$del = (strtotime($msg['validity']) < time()) ? true : false;

		echo '<tr';
		echo ($del) ? ' class="danger"' : '';
		echo '>';
		echo '<td>';

		echo ($msg['msg_type']) ? 'Aanbod' : 'Vraag';
		echo '</td>';

		echo '<td>';
		echo aphp('messages', 'id=' . $msg['id'], $msg['content']);
		echo '</td>';

		echo '<td>';
		echo $msg['validity_short'];
		echo '</td>';

		echo '<td>';
		echo link_user($msg['id_user']);
		echo '</td>';

		echo '<td>';
		echo aphp('messages', 'cid=' . $msg['cid'], $msg['cat']);
		echo '</td>';

		echo '<td>';
		echo $msg['postcode'];
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div>';
	echo '</div>';
}

include $rootpath . 'includes/inc_footer.php';
