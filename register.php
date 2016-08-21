<?php

$page_access = 'anonymous';

require_once __DIR__ . '/includes/inc_default.php';

$submit = isset($_POST['zend']) ? true : false;
$token = $_GET['token'] ?? false;

if ($s_id)
{
	header('Location: ' . $rootpath . 'index.php');
	exit;
}

if (!readconfigfromdb('registration_en'))
{
	$app['eland.alert']->warning('De inschrijvingspagina is niet ingeschakeld.');
	redirect_login();
}

if ($token)
{
	$key = $schema . '_register_' . $token;

	if ($data = $app['redis']->get($key))
	{
		$data = json_decode($data, true);
		$app['redis']->del($key);

//		$letscode = '--' . substr($token, 0, 5);

		for ($i = 0; $i < 60; $i++)
		{
			$name = $data['first_name'];

			if ($i)
			{
				$name .= ' ';

				if ($i < strlen($data['last_name']))
				{
					$name .= substr($data['last_name'], 0, $i);
				}
				else
				{
					$name .= substr(hash('sha512', $schema . time() . mt_rand(0, 100000), 0, 4));
				}
			}

			if (!$app['db']->fetchColumn('select name from users where name = ?', [$name]))
			{
				break;
			}
		}

		$user = [
			'name'			=> $name,
			'fullname'		=> $data['first_name'] . ' ' . $data['last_name'],
			'postcode'		=> $data['postcode'],
//			'letscode'		=> '',
			'login'			=> sha1(microtime()),
			'minlimit'		=> readconfigfromdb('minlimit'),
			'maxlimit'		=> readconfigfromdb('maxlimit'),
			'status'		=> 5,
			'accountrole'	=> 'user',
			'cron_saldo'	=> 't',
			'lang'			=> 'nl',
			'hobbies'		=> '',
			'cdate'			=> gmdate('Y-m-d H:i:s'),
		];

		$app['db']->beginTransaction();

		try
		{
			$app['db']->insert('users', $user);

			$user_id = $app['db']->lastInsertId('users_id_seq');

			$tc = [];

			$rs = $app['db']->prepare('select abbrev, id from type_contact');

			$rs->execute();

			while($row = $rs->fetch())
			{
				$tc[$row['abbrev']] = $row['id'];
			}

			$mail = [
				'id_user'			=> $user_id,
				'flag_public'		=> 0,
				'value'				=> $data['email'],
				'id_type_contact'	=> $tc['mail'],
			];

			$app['db']->insert('contact', $mail);

			if ($data['gsm'] || $data['tel'])
			{
				if ($data['gsm'])
				{
					$gsm = [
						'id_user'			=> $user_id,
						'flag_public'		=> 0,
						'value'				=> $data['gsm'],
						'id_type_contact'	=> $tc['gsm'],
					];

					$app['db']->insert('contact', $gsm);
				}

				if ($data['tel'])
				{
					$tel = [
						'id_user'			=> $user_id,
						'flag_public'		=> 0,
						'value'				=> $data['tel'],
						'id_type_contact'	=> $tc['tel'],
					];
					$app['db']->insert('contact', $tel);
				}
			}
			$app['db']->commit();
		}
		catch (Exception $e)
		{
			$app['db']->rollback();
			throw $e;
		}

		$subject = 'nieuwe inschrijving: ' . $user['fullname'];

		$text = '*** Dit is een automatische mail van ' . readconfigfromdb('systemname') . " *** \n\n";
		$text .= "De volgende persoon schreef zich in via de website: \n\n";
		$text .= 'Volledige naam: ' . $user['fullname'] . "\n";
		$text .= 'Postcode: ' . $user['postcode'] . "\n";
		$text .= 'Email: ' . $data['email'] . "\n\n";
		$text .= 'Link: ' . $base_url . '/users.php?id=' . $user_id;

		mail_q(['to' => 'admin', 'subject' => $subject, 'text' => $text]);

		$registration_success_mail = readconfigfromdb('registration_success_mail');

		if ($registration_success_mail)
		{
			$subject = 'Inschrijving';

			$map_template_vars = [
				'voornaam' 			=> 'first_name',
				'achternaam'		=> 'last_name',
				'postcode'			=> 'postcode',
			];

			foreach ($map_template_vars as $k => $v)
			{
				$map_template_vars[$k] = $data[$v];
			}

			try
			{
				$template = $app['twig']->createTemplate($registration_success_mail);
				$html = $template->render($map_template_vars);
			}
			catch (Exception $e)
			{
				$app['eland.alert']->error('Fout in mail template: ' . $e->getMessage());
			}

			mail_q(['to' => $data['email'], 'subject' => $subject, 'html' => $html, 'reply_to' => 'admin']);
		}

		$app['eland.alert']->success('Inschrijving voltooid.');

		require_once __DIR__ . '/includes/inc_header.php';

		$registration_success_text = readconfigfromdb('registration_success_text');

		if ($registration_success_text)
		{
			echo $registration_success_text;
		}

		require_once __DIR__ . '/includes/inc_footer.php';

		exit;
	}

	$app['eland.alert']->error('Geen geldig token.');

	require_once __DIR__ . '/includes/inc_header.php';

	echo '<div class="panel panel-danger">';
	echo '<div class="panel-heading">';

	echo '<h2>Registratie niet gelukt</h2>';

	echo '</div>';
	echo '<div class="panel-body">';

	echo aphp('register', [], 'Opnieuw proberen', 'btn btn-default');

	echo '</div>';
	echo '</div>';

	require_once __DIR__ . '/includes/inc_footer.php';
	exit;
}

if ($submit)
{
	$reg = [
		'email'			=> $_POST['email'],
		'first_name'	=> $_POST['first_name'],
		'last_name'		=> $_POST['last_name'],
		'postcode'		=> $_POST['postcode'],
		'tel'			=> $_POST['tel'],
		'gsm'			=> $_POST['gsm'],
	];

	log_event('system', 'Registration request for ' . $reg['email']);

	if(!$reg['email'])
	{
		$app['eland.alert']->error('Vul een email adres in.');
	}
	else if (!filter_var($reg['email'], FILTER_VALIDATE_EMAIL))
	{
		$app['eland.alert']->error('Geen geldig email adres.');
	}
	else if ($app['db']->fetchColumn('select c.id_user
		from contact c, type_contact tc
		where c. value = ?
			AND tc.id = c.id_type_contact
			AND tc.abbrev = \'mail\'', [$reg['email']]))
	{
		$app['eland.alert']->error('Er bestaat reeds een inschrijving met dit mailadres.');
	}
	else if (!$reg['first_name'])
	{
		$app['eland.alert']->error('Vul een voornaam in.');
	}
	else if (!$reg['last_name'])
	{
		$app['eland.alert']->error('Vul een achternaam in.');
	}
	else if (!$reg['postcode'])
	{
		$app['eland.alert']->error('Vul een postcode in.');
	}
	else if ($error_token = $app['eland.form_token']->get_error())
	{
		$app['eland.alert']->error($error_token);
	}
	else
	{
		$token = substr(hash('sha512', $schema . microtime() . $reg['email'] . $reg['first_name']), 0, 10);
		$key = $schema . '_register_' . $token;
		$app['redis']->set($key, json_encode($reg));
		$app['redis']->expire($key, 86400);
		$key = $schema . '_register_email_' . $email;
		$app['redis']->set($key, '1');
		$app['redis']->expire($key, 86400);
		$subject = 'Bevestig je inschrijving voor ' . readconfigfromdb('systemname');
		$url = $base_url . '/register.php?token=' . $token;
		$text = 'Inschrijven voor ' . readconfigfromdb('systemname') . "\n\n";
		$text .= "Klik op deze link om je inschrijving  te bevestigen :\n\n" . $url . "\n\n";
		$text .= "Deze link blijft 1 dag geldig.\n\n";

		mail_q(['to' => $reg['email'], 'subject' => $subject, 'text' => $text]);

		$app['eland.alert']->warning('Open je mailbox en klik op de bevestigingslink in de email die we naar je gestuurd hebben om je inschrijving te voltooien.');
		header('Location: ' . $rootpath . 'login.php');
		exit;
	}
}

$h1 = 'Inschrijven';
$fa = 'check-square-o';

require_once __DIR__ . '/includes/inc_header.php';

$top_text = readconfigfromdb('registration_top_text');

if ($top_text)
{
	echo $top_text;
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="first_name" class="col-sm-2 control-label">Voornaam*</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="first_name" name="first_name" ';
echo 'value="';
echo $reg['first_name'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="last_name" class="col-sm-2 control-label">Achternaam*</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="last_name" name="last_name" ';
echo 'value="';
echo $reg['last_name'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="email" class="col-sm-2 control-label">Email*</label>';
echo '<div class="col-sm-10">';
echo '<input type="email" class="form-control" id="email" name="email" ';
echo 'value="';
echo $reg['email'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="postcode" class="col-sm-2 control-label">Postcode*</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="postcode" name="postcode" ';
echo 'value="';
echo $reg['postcode'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="gsm" class="col-sm-2 control-label">Gsm</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="gsm" name="gsm" ';
echo 'value="';
echo $reg['gsm'] ?? '';
echo  '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="tel" class="col-sm-2 control-label">Telefoon</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="tel" name="tel" ';
echo 'value="';
echo $reg['tel'] ?? '';
echo '">';
echo '</div>';
echo '</div>';

echo '<input type="submit" class="btn btn-default" value="Inschrijven" name="zend">';
$app['eland.form_token']->generate();

echo '</form>';

echo '</div>';
echo '</div>';

$bottom_text = readconfigfromdb('registration_bottom_text');

if ($bottom_text)
{
	echo $bottom_text;
}

require_once __DIR__ . '/includes/inc_footer.php';
exit;
