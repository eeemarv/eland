<?php

$page_access = 'anonymous';

require_once __DIR__ . '/include/web.php';

$token = $_GET['token'] ?? false;
$login = $_GET['login'] ?? '';
$monitor = $_GET['monitor'] ?? false;
$location = $_GET['location'] ?? false;

if (!$location
	|| strpos($location, 'login.php') !== false
	|| strpos($location, 'logout.php') !== false
	|| $location == ''
	|| $location == '/')
{
	$location = $app['config']->get('default_landing_page', $app['tschema']);
	$location .= '.php';
}

$submit = isset($_POST['zend']);

if ($monitor)
{
	$app['monitor_process']->monitor();
	exit;
}

if ($token)
{
	if($apikey = $app['predis']->get($app['tschema'] . '_token_' . $token))
	{
		$app['s_logins'] = $app['session']->get('logins') ?? [];
		$app['s_logins'] = array_merge($app['s_logins'], [
			$app['tschema'] 	=> 'elas',
		]);
		$app['session']->set('logins', $app['s_logins']);

		$param = 'welcome=1&r=guest&u=elas';

		$referrer = $_SERVER['HTTP_REFERER'] ?? 'unknown';

		if ($referrer !== 'unknown')
		{
			// record logins to link the apikeys to domains and systems
			$domain_referrer = strtolower(parse_url($referrer, PHP_URL_HOST));
			$app['xdb']->set('apikey_login', $apikey, [
				'domain' => $domain_referrer
			], $app['tschema']);
		}

		$app['monolog']->info('eLAS guest login using token ' .
			$token . ' succeeded. referrer: ' . $referrer,
			['schema' => $app['tschema']]);

		$glue = strpos($location, '?') === false ? '?' : '&';
		header('Location: ' . $location . $glue . $param);
		exit;
	}
	else
	{
		$app['alert']->error('De interSysteem login is mislukt.');
	}
}

if ($submit)
{
	$login = trim(strtolower($_POST['login']));
	$password = trim($_POST['password']);

	if (!($login && $password))
	{
		$errors[] = 'Login gefaald. Vul Login en Paswoord in.';
	}

	$master_password = getenv('MASTER_PASSWORD');

	if ($login == 'master'
		&& $master_password
		&& hash('sha512', $password) === $master_password)
	{
		$app['s_logins'] = $app['session']->get('logins') ?? [];
		$app['s_logins'] = array_merge($app['s_logins'], [
			$app['tschema'] 	=> 'master',
		]);
		$app['session']->set('logins', $app['s_logins']);

		$app['alert']->success('OK - Gebruiker ingelogd als master.');
		$glue = strpos($location, '?') === false ? '?' : '&';
		header('Location: ' . $location . $glue . 'a=1&r=admin&u=master');
		exit;
	}

	$user_id = false;

	if (!count($errors) && filter_var($login, FILTER_VALIDATE_EMAIL))
	{
		$count_email = $app['db']->fetchColumn('select count(c.*)
			from ' . $app['tschema'] . '.contact c, ' .
				$app['tschema'] . '.type_contact tc, ' .
				$app['tschema'] . '.users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.id_user = u.id
				and u.status in (1, 2)
				and lower(c.value) = ?', [$login]);

		if ($count_email == 1)
		{
			$user_id = $app['db']->fetchColumn('select u.id
				from ' . $app['tschema'] . '.contact c, ' .
					$app['tschema'] . '.type_contact tc, ' .
					$app['tschema'] . '.users u
				where c.id_type_contact = tc.id
					and tc.abbrev = \'mail\'
					and c.id_user = u.id
					and u.status in (1, 2)
					and lower(c.value) = ?', [$login]);
		}
		else
		{
			$err = 'Je kan dit E-mail adres niet gebruiken ';
			$err .= 'om in te loggen want het is niet ';
			$err .= 'uniek aanwezig in dit Systeem. Gebruik ';
			$err .= 'je Account Code of Gebruikersnaam.';
			$errors[] = $err;
		}
	}

	if (!$user_id && !count($errors))
	{
		$count_letscode = $app['db']->fetchColumn('select count(u.*)
			from ' . $app['tschema'] . '.users u
			where lower(letscode) = ?', [$login]);

		if ($count_letscode > 1)
		{
			$err = 'Je kan deze Account Code niet gebruiken ';
			$err .= 'om in te loggen want deze is niet ';
			$err .= 'uniek aanwezig in dit Systeem. Gebruik ';
			$err .= 'je E-mail adres of gebruikersnaam.';
			$errors[] = $err;
		}
		else if ($count_letscode == 1)
		{
			$user_id = $app['db']->fetchColumn('select id
				from ' . $app['tschema'] . '.users
				where lower(letscode) = ?', [$login]);
		}
	}

	if (!$user_id && !count($errors))
	{
		$count_name = $app['db']->fetchColumn('select count(u.*)
			from ' . $app['tschema'] . '.users u
			where lower(name) = ?', [$login]);

		if ($count_name > 1)
		{
			$err = 'Je kan deze gebruikersnaam niet gebruiken ';
			$err .= 'om in te loggen want deze is niet ';
			$err .= 'uniek aanwezig in dit Systeem. Gebruik ';
			$err .= 'je E-mail adres of Account Code.';
			$errors[] = $err;
		}
		else if ($count_name == 1)
		{
			$user_id = $app['db']->fetchColumn('select id
				from ' . $app['tschema'] . '.users
				where lower(name) = ?', [$login]);
		}
	}

	if (!$user_id && !count($errors))
	{
		$errors[] = 'Login gefaald. Onbekende gebruiker.';
	}
	else if ($user_id && !count($errors))
	{
		$user = $app['user_cache']->get($user_id, $app['tschema']);

		if (!$user)
		{
			$errors[] = 'Onbekende gebruiker.';
		}
		else
		{
			$log_ary = [
				'user_id'	=> $user['id'],
				'letscode'	=> $user['letscode'],
				'username'	=> $user['name'],
				'schema' 	=> $app['tschema'],
			];

			$sha512 = hash('sha512', $password);
			$sha1 = sha1($password);
			$md5 = md5($password);

			if (!in_array($user['password'], [$sha512, $sha1, $md5]))
			{
				$errors[] = 'Het paswoord is niet correct.';
			}
			else if ($user['password'] != $sha512)
			{
				$app['db']->update($app['tschema'] . '.users',
					['password' => hash('sha512', $password)],
					['id' => $user['id']]);

				$app['monolog']->info('Password encryption updated to sha512', $log_ary);
			}
		}
	}

	if (!count($errors) && !in_array($user['status'], [1, 2]))
	{
		$errors[] = 'Het account is niet actief.';
	}

	if (!count($errors) && !in_array($user['accountrole'], ['user', 'admin']))
	{
		$errors[] = 'Het account beschikt niet over de juiste rechten.';
	}

	if (!count($errors)
		&& $app['config']->get('maintenance', $app['tschema'])
		&& $user['accountrole'] != 'admin')
	{
		$errors[] = 'De website is in onderhoud, probeer later opnieuw';
	}

	if (!count($errors))
	{
		$app['s_logins'] = $app['session']->get('logins') ?? [];
		$app['s_logins'] = array_merge($app['s_logins'], [
			$app['tschema'] 	=> $user['id'],
		]);
		$app['session']->set('logins', $app['s_logins']);

		$app['s_id'] = $user['id'];
		$app['s_schema'] = $app['tschema'];

		$browser = $_SERVER['HTTP_USER_AGENT'];

		$app['monolog']->info('User ' .
			link_user($user, $app['tschema'], false, true) .
			' logged in, agent: ' . $browser, $log_ary);

		$app['db']->update($app['tschema'] . '.users',
			['lastlogin' => gmdate('Y-m-d H:i:s')],
			['id' => $user['id']]);

		$app['user_cache']->clear($user['id'], $app['tschema']);

		$app['xdb']->set('login', $user['id'], [
			'browser' => $browser, 'time' => time()
		], $app['s_schema']);

		$app['alert']->success('Je bent ingelogd.');

		$glue = strpos($location, '?') === false ? '?' : '&';

		header('Location: ' . $location . $glue . 'a=1&r=' . $user['accountrole'] . '&' . 'u=' .  $user['id']);
		exit;
	}

	$app['alert']->error($errors);
}

if($app['config']->get('maintenance', $app['tschema']))
{
	$app['alert']->warning('De website is niet beschikbaar
		wegens onderhoudswerken.  Enkel admins kunnen inloggen');
}

$h1 = 'Login';
$fa = 'sign-in';

require_once __DIR__ . '/include/header.php';

if(empty($token))
{
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="login">';
	echo 'Login</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-user"></i>';
	echo '</span>';
    echo '<input type="text" class="form-control" id="login" name="login" ';
	echo 'value="';
	echo $login;
	echo '" required>';
    echo '</div>';
	echo '<p>';
	echo 'E-mail, Account Code of Gebruikersnaam';
	echo '</p>';
	echo '</div>';

	echo '<div class="form-group">';
    echo '<label for="password">Paswoord</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-key"></i>';
	echo '</span>';
	echo '<input type="password" class="form-control" ';
	echo 'id="password" name="password" ';
	echo 'value="" required>';
    echo '</div>';
	echo '<p>';
	echo aphp('pwreset', [], 'Klik hier als je je paswoord vergeten bent.');
	echo '</p>';
	echo '</div>';

	echo '<input type="submit" class="btn btn-default" ';
	echo 'value="Inloggen" name="zend">';

	echo '</form>';

	echo '</div>';
	echo '</div>';
}

include __DIR__ . '/include/footer.php';
