<?php

use Gregwar\Captcha\CaptchaBuilder;

$page_access = 'anonymous';

require_once __DIR__ . '/include/web.php';

$submit = isset($_POST['zend']) ? true : false;
$token = $_GET['token'] ?? false;

if ($app['s_id'])
{
	redirect_default_page();
}

if (!$app['config']->get('registration_en', $app['tschema']))
{
	$app['alert']->warning('De inschrijvingspagina is niet ingeschakeld.');
	redirect_login();
}

if ($token)
{
	$key = $app['tschema'];
	$key .= '_register_' . $token;

	if ($data = $app['predis']->get($key))
	{
		$data = json_decode($data, true);
		$app['predis']->del($key);

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
					$name .= substr(hash('sha512', $app['tschema'] . time() . mt_rand(0, 100000)), 0, 4);
				}
			}

			if (!$app['db']->fetchColumn('select name
				from ' . $app['tschema'] . '.users
				where name = ?', [$name]))
			{
				break;
			}
		}

		$minlimit = $app['config']->get('preset_minlimit', $app['tschema']);
		$minlimit = $minlimit === '' ? -999999999 : $minlimit;

		$maxlimit = $app['config']->get('preset_maxlimit', $app['tschema']);
		$maxlimit = $maxlimit === '' ? 999999999 : $maxlimit;

		$user = [
			'name'			=> $name,
			'fullname'		=> $data['first_name'] . ' ' . $data['last_name'],
			'postcode'		=> $data['postcode'],
//			'letscode'		=> '',
			'login'			=> sha1(microtime()),
			'minlimit'		=> $minlimit,
			'maxlimit'		=> $maxlimit,
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
			$app['db']->insert($app['tschema'] . '.users', $user);

			$user_id = $app['db']->lastInsertId($app['tschema'] . '.users_id_seq');

			$tc = [];

			$rs = $app['db']->prepare('select abbrev, id
				from ' . $app['tschema'] . '.type_contact');

			$rs->execute();

			while($row = $rs->fetch())
			{
				$tc[$row['abbrev']] = $row['id'];
			}

			$data['email'] = strtolower($data['email']);

			$mail = [
				'id_user'			=> $user_id,
				'flag_public'		=> 0,
				'value'				=> $data['email'],
				'id_type_contact'	=> $tc['mail'],
			];

			$app['db']->insert($app['tschema'] . '.contact', $mail);

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

					$app['db']->insert($app['tschema'] . '.contact', $gsm);
				}

				if ($data['tel'])
				{
					$tel = [
						'id_user'			=> $user_id,
						'flag_public'		=> 0,
						'value'				=> $data['tel'],
						'id_type_contact'	=> $tc['tel'],
					];
					$app['db']->insert($app['tschema'] . '.contact', $tel);
				}
			}
			$app['db']->commit();
		}
		catch (Exception $e)
		{
			$app['db']->rollback();
			throw $e;
		}

		$vars = [
			'user_id'		=> $user_id,
			'postcode'		=> $user['postcode'],
			'email'			=> $data['email'],
		];

		$app['queue.mail']->queue([
			'schema'		=> $app['tschema'],
			'to' 			=> $app['mail_addr_system']->get_admin($app['tschema']),
			'vars'			=> $vars,
			'template'		=> 'register/admin',
		], 8000);

		$map_template_vars = [
			'voornaam' 			=> 'first_name',
			'achternaam'		=> 'last_name',
			'postcode'			=> 'postcode',
		];

		foreach ($map_template_vars as $k => $v)
		{
			$vars[$k] = $data[$v];
		}

		$vars['subject'] = $app['translator']->trans('register_success.subject', [
			'%system_name%'	=> $app['config']->get('systemname', $app['tschema']),
		], 'mail');

		$app['queue.mail']->queue([
			'schema'				=> $app['tschema'],
			'to' 					=> [$data['email'] => $user['fullname']],
			'reply_to'				=> $app['mail_addr_system']->get_admin($app['tschema']),
			'pre_html_template'		=> $app['config']->get('registration_success_mail', $app['tschema']),
			'template'				=> 'skeleton',
			'vars'					=> $vars,
		], 8500);

		$app['alert']->success('Inschrijving voltooid.');

		require_once __DIR__ . '/include/header.php';

		$registration_success_text = $app['config']->get('registration_success_text', $app['tschema']);

		if ($registration_success_text)
		{
			echo $registration_success_text;
		}

		require_once __DIR__ . '/include/footer.php';

		exit;
	}

	$app['alert']->error('Geen geldig token.');

	require_once __DIR__ . '/include/header.php';

	echo '<div class="panel panel-danger">';
	echo '<div class="panel-heading">';

	echo '<h2>Registratie niet gelukt</h2>';

	echo '</div>';
	echo '<div class="panel-body">';

	echo aphp('register', [], 'Opnieuw proberen', 'btn btn-default');

	echo '</div>';
	echo '</div>';

	require_once __DIR__ . '/include/footer.php';
	exit;
}

if ($submit)
{
	$reg = [
		'email'			=> $_POST['email'] ?? '',
		'first_name'	=> $_POST['first_name'] ?? '',
		'last_name'		=> $_POST['last_name'] ?? '',
		'postcode'		=> $_POST['postcode'] ?? '',
		'tel'			=> $_POST['tel'] ?? '',
		'gsm'			=> $_POST['gsm'] ?? '',
	];

	$captcha_key = 'captcha_';
	$captcha_key .= $app['form_token']->get_posted();
	$captcha_key .= '_';
	$captcha_key .= '_' . $_POST['captcha'];

	$app['monolog']->info('Registration request for ' .
		$reg['email'], ['schema' => $app['tschema']]);

	if(!$app['predis']->get($captcha_key))
	{
		$app['alert']->error('De verificatiecode werd niet correct ingevuld.');
	}
	else if(!$reg['email'])
	{
		$app['alert']->error('Vul een E-mail adres in.');
	}
	else if (!filter_var($reg['email'], FILTER_VALIDATE_EMAIL))
	{
		$app['alert']->error('Geen geldig E-mail adres.');
	}
	else if ($app['db']->fetchColumn('select c.id_user
		from ' . $app['tschema'] . '.contact c, ' .
			$app['tschema'] . '.type_contact tc
		where c. value = ?
			AND tc.id = c.id_type_contact
			AND tc.abbrev = \'mail\'', [$reg['email']]))
	{
		$app['alert']->error('Er bestaat reeds een inschrijving
			met dit E-mail adres.');
	}
	else if (!$reg['first_name'])
	{
		$app['alert']->error('Vul een Voornaam in.');
	}
	else if (!$reg['last_name'])
	{
		$app['alert']->error('Vul een Achternaam in.');
	}
	else if (!$reg['postcode'])
	{
		$app['alert']->error('Vul een Postcode in.');
	}
	else if ($error_token = $app['form_token']->get_error())
	{
		$app['alert']->error($error_token);
	}
	else
	{
		$token = substr(hash('sha512', $app['tschema'] . microtime() . $reg['email'] . $reg['first_name']), 0, 10);
		$key = $app['tschema'] . '_register_' . $token;
		$app['predis']->set($key, json_encode($reg));
		$app['predis']->expire($key, 604800); // 1 week
		$key = $app['tschema'] . '_register_email_' . $reg['email'];
		$app['predis']->set($key, '1');
		$app['predis']->expire($key, 604800);

		$vars = [
			'token'		=> $token,
		];

		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'to' 		=> [$reg['email'] => $reg['first_name'] . ' ' . $reg['last_name']],
			'vars'		=> $vars,
			'template'	=> 'register/confirm',
		], 10000);

		$app['alert']->success('Open je E-mailbox en klik op de
			bevestigingslink in de E-mail die we naar je gestuurd
			hebben om je inschrijving te voltooien.');
		header('Location: ' . $app['rootpath'] . 'login.php');
		exit;
	}
}

$captcha = new CaptchaBuilder;
$captcha->setDistortion(false);
$captcha->setIgnoreAllEffects(true);
$captcha->build();
$captcha_inline = $captcha->inline();
$captcha_phrase = $captcha->getPhrase();
$captcha_key = 'captcha_';
$captcha_key .= $app['form_token']->get();
$captcha_key .= '_';
$captcha_key .= '_' . $captcha->getPhrase();
$app['predis']->set($captcha_key, '1');
$app['predis']->expire($captcha_key, 14400);

$h1 = 'Inschrijven';
$fa = 'check-square-o';

require_once __DIR__ . '/include/header.php';

$top_text = $app['config']->get('registration_top_text', $app['tschema']);

if ($top_text)
{
	echo $top_text;
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post">';

echo '<div class="form-group">';
echo '<label for="first_name" class="control-label">Voornaam*</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-user"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="first_name" name="first_name" ';
echo 'value="';
echo $reg['first_name'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="last_name" class="control-label">Achternaam*</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-user"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="last_name" name="last_name" ';
echo 'value="';
echo $reg['last_name'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="email" class="control-label">E-mail*</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-envelope-o"></i>';
echo '</span>';
echo '<input type="email" class="form-control" id="email" name="email" ';
echo 'value="';
echo $reg['email'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="postcode" class="control-label">Postcode*</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-map-marker"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="postcode" name="postcode" ';
echo 'value="';
echo $reg['postcode'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="gsm" class="control-label">Gsm</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-mobile"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="gsm" name="gsm" ';
echo 'value="';
echo $reg['gsm'] ?? '';
echo  '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="tel" class="control-label">Telefoon</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-phone"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="tel" name="tel" ';
echo 'value="';
echo $reg['tel'] ?? '';
echo '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="captcha">';
echo 'Anti-spam verificatiecode*';
echo '</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-code"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="captcha" name="captcha" ';
echo 'value="" required>';
echo '</div>';
echo '<p>';
echo 'Type de code in die hieronder getoond wordt.';
echo '</p>';
echo '<img src="';
echo $captcha_inline;
echo '" alt="Code niet geladen.">';
echo '</div>';

echo '<input type="submit" class="btn btn-default" value="Inschrijven" name="zend">';
echo $app['form_token']->get_hidden_input();

echo '</form>';

echo '</div>';
echo '</div>';

$bottom_text = $app['config']->get('registration_bottom_text', $app['tschema']);

if ($bottom_text)
{
	echo $bottom_text;
}

require_once __DIR__ . '/include/footer.php';
exit;
