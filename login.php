<?php

$rootpath = './';
$page_access = 'anonymous';

require_once $rootpath . 'includes/inc_default.php';

/*
if ($s_id)
{
	header('Location: ' . generate_url('index'));
	exit;
}
*/

$token = $_GET['token'] ?? false;
$login = $_GET['login'] ?? '';

$location = $_GET['location'] ?? false;
$location = ($location) ? urldecode($location) : '/index.php';
$location = (strpos($location, 'login.php') === false) ? $location : 'index.php';
$location = (strpos($location, 'logout.php') === false) ? $location : 'index.php';

$submit = isset($_POST['zend']) ? true : false;

if($token)
{
	if($interlets = $redis->get($schema . '_token_' . $token))
	{
		$_SESSION['logins'][$schema] = 'elas';

		$param = 'welcome=1&r=guest&u=elas';

		$referer = $_SERVER['HTTP_REFERER'] ?? 'unknown';

		log_event('login', 'eLAS guest login using token ' . $token . ' succeeded. referer: ' . $referer);

		$glue = (strpos($location, '?') === false) ? '?' : '&';
		header('Location: ' . $location . $glue . $param);
		exit;
	}
	else
	{
		$alert->error('De interlets login is mislukt.');
		log_event('', 'login-fail', 'Token login failed (' . $token . ')');
	}
}

if ($submit)
{
	$login = trim($_POST['login']);
	$password = trim($_POST['password']);

	if (!($login && $password))
	{
		$errors[] = 'Login gefaald. Vul login en paswoord in.';
	}

	$master_password = getenv('MASTER_PASSWORD');

	if ($login == 'master' && hash('sha512', $password) == $master_password)
	{
		$_SESSION['logins'][$schema] = 'master';

		log_event('login','Master user logged in');
		$alert->success('OK - Gebruiker ingelogd als master.');
		$glue = (strpos($location, '?') === false) ? '?' : '&';
		header('Location: ' . $location . $glue . 'a=1&r=admin&u=master');
		exit;
	}

	$user_id = false;

	if (!count($errors) && filter_var($login, FILTER_VALIDATE_EMAIL))
	{
		$count_email = $db->fetchColumn('select count(c.*)
			from contact c, type_contact tc, users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.id_user = u.id
				and u.status in (1, 2)
				and c.value = ?', [$login]);

		if ($count_email == 1)
		{
			$user_id = $db->fetchColumn('select u.id
				from contact c, type_contact tc, users u
				where c.id_type_contact = tc.id
					and tc.abbrev = \'mail\'
					and c.id_user = u.id
					and u.status in (1, 2)
					and c.value = ?', [$login]);
		}
		else
		{
			$err = 'Je kan dit email adres niet gebruiken om in te loggen want het is niet ';
			$err .= 'uniek aanwezig in deze installatie. Gebruik je letscode of gebruikersnaam.';
			$errors[] = $err;
		}
	}

	if (!$user_id && !count($errors))
	{
		$count_letscode = $db->fetchColumn('select count(u.*)
			from users u
			where letscode = ?', [$login]);

		if ($count_letscode > 1)
		{
			$err = 'Je kan deze letscode niet gebruiken om in te loggen want deze is niet ';
			$err .= 'uniek aanwezig in deze installatie. Gebruik je mailadres of gebruikersnaam.';
			$errors[] = $err;
		}
		else if ($count_letscode == 1)
		{
			$user_id = $db->fetchColumn('select id from users where letscode = ?', [$login]);
		}
	}

	if (!$user_id && !count($errors))
	{
		$count_name = $db->fetchColumn('select count(u.*)
			from users u
			where name = ?', [$login]);

		if ($count_name > 1)
		{
			$err = 'Je kan deze gebruikersnaam niet gebruiken om in te loggen want deze is niet ';
			$err .= 'uniek aanwezig in deze installatie. Gebruik je mailadres of letscode.';
			$errors[] = $err;
		}
		else if ($count_name == 1)
		{
			$user_id = $db->fetchColumn('select id from users where name = ?', [$login]);
		}
	}

	if (!$user_id && !count($errors))
	{
		$errors[] = 'Login gefaald. Onbekende gebruiker.';
	}
	else if ($user_id && !count($errors))
	{
		$user = readuser($user_id);

		if (!$user)
		{
			$errors[] = 'Onbekende gebruiker.';
		}
		else
		{
			$sha512 = hash('sha512', $password);
			$sha1 = sha1($password);
			$md5 = md5($password);

			if (!in_array($user['password'], [$sha512, $sha1, $md5]))
			{
				$errors[] = 'Het paswoord is niet correct.';
			}
			else if ($user['password'] != $sha512)
			{
				$db->update('users', ['password' => hash('sha512', $password)], ['id' => $user['id']]);
				log_event('password', 'Password encryption updated to sha512');
			}
		}
	}

	if (!count($errors) && !in_array($user['status'], [1, 2]))
	{
		$errors[] = 'Het account is niet actief.';
	}

	if (!count($errors) && !in_array($user['accountrole'], ['user', 'admin', 'interlets']))
	{
		$errors[] = 'Het account beschikt niet over de juiste rechten.';
	}

	if (!count($errors) && readconfigfromdb('maintenance') && $user['accountrole'] != 'admin')
	{
		$errors[] = 'De website is in onderhoud, probeer later opnieuw';
	}

	if (!count($errors))
	{
		$_SESSION['logins'][$schema] = $user['id'];

		$s_id = $user['id'];
		$s_schema = $schema;		

		$browser = $_SERVER['HTTP_USER_AGENT'];

		log_event('login','User ' . link_user($user, false, false, true) . ' logged in');
		log_event('agent', $browser);

		$db->update('users', ['lastlogin' => gmdate('Y-m-d H:i:s')], ['id' => $user['id']]);
		readuser($user['id'], true);

		$alert->success('Je bent ingelogd.');

		$glue = (strpos($location, '?') === false) ? '?' : '&';

		$accountrole = ($user['accountrole'] == 'interlets') ? 'guest' : $user['accountrole'];

		header('Location: ' . $location . $glue . 'a=1&r=' . $accountrole . '&' . 'u=' .  $user['id']);
		exit;
	}

	$alert->error($errors);
}

if(readconfigfromdb('maintenance'))
{
	$alert->warning('De website is niet beschikbaar wegens onderhoudswerken.  Enkel admin gebruikers kunnen inloggen');
}

$h1 = 'Login';
$fa = 'sign-in';

require_once $rootpath . 'includes/inc_header.php';

if(empty($token))
{
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
    echo '<label for="login" class="col-sm-2 control-label">Email, letscode of gebruikersnaam</label>';
    echo '<div class="col-sm-10">';
    echo '<input type="text" class="form-control" id="login" name="login" ';
    echo 'value="' . $login . '" required>';
    echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
    echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
    echo '<div class="col-sm-10">';
    echo '<input type="password" class="form-control" id="password" name="password" ';
    echo 'value="" required>';
    echo '</div>';
	echo '</div>';

	echo '<input type="submit" class="btn btn-default" value="Inloggen" name="zend">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	echo aphp('pwreset', [], 'Ik ben mijn paswoord vergeten');
}

include $rootpath . 'includes/inc_footer.php';
