<?php

$rootpath = './';
$role = 'anonymous';
$allow_anonymous_post = true;

require_once $rootpath . 'includes/inc_default.php';

if ($s_id)
{
	header('Location: ' . generate_url('index'));
	exit;
}

$token = $_GET['token'];
$login = $_GET['login'];
$location = $_GET['location'];
$location = ($location) ? urldecode($location) : '/index.php';
$location = (strpos($location, 'login.php') === false) ? $location : 'index.php';
$location = (strpos($location, 'logout.php') === false) ? $location : 'index.php';
$error_location = 'login.php?location=' . urlencode($location);

if(!empty($token))
{
	if($interlets = $redis->get($schema . '_token_' . $token))
	{
		$_SESSION = array(
			'id'			=> 0,
			'letscode'		=> '-',
			'accountrole'	=> 'guest',
			'type'			=> 'interlets',
		);

		$param = 'a=1&r=guest';

		if ($interlets != '1')
		{
			$interlets = unserialize($interlets);

			$_SESSION['interlets'] = $interlets;
			$_SESSION['name'] = 'letsgast: ' . $interlets['systemtag'] . '.' . $interlets['letscode'] . ' ' . $interlets['name'];
			$param .= '&u=' . $interlets['id'] . '&s=' . $interlets['schema'];
		}
		else
		{
			$_SESSION['name'] = 'letsgast';
		}

		log_event(0, 'Login', 'Guest login (' . $_SESSION['name'] . ') using token ' . $token . ' succeeded');
		$alert->success($_SESSION['name'] . ' ingelogd');

		$glue = (strpos($location, '?') === false) ? '?' : '&';
		header('Location: ' . $location . $glue . $param);
		exit;
	}
	else
	{
		$alert->error('Interlets login is mislukt.');
		log_event('', 'LogFail', 'Token login failed (' . $token . ')');
	}
}

if ($_POST['zend'])
{
	$login = trim($_POST['login']);
	$password = trim($_POST['password']);

	$errors = array();

	if (!($login && $password))
	{
		$errors[] = 'Login gefaald. Vul login en paswoord in.';
	}

	$master_password = getenv('ELAS_MASTER_PASSWORD');

	if ($login == 'master' && hash('sha512', $password) == $master_password)
	{
		$_SESSION = array(
			'id'				=> 0,
			'name'				=> 'master',
			'fullname'			=> 'eLAS Master',
			'login'				=> 'master',
			'user_postcode'		=> '0000',
			'letscode'			=> '000000',
			'accountrole'		=> 'admin',
			'userstatus'		=> 1,
			'email'				=> '',
			'lang'				=> 'nl',
			'type'				=> 'master',
		);
		log_event(0,'Login','Master user ' . $user['login'] . ' logged in');
		$alert->success('OK - Gebruiker ingelogd als master.');
		$glue = (strpos($location, '?') === false) ? '?' : '&';
		header('Location: ' . $location . $glue . 'a=1&r=admin&u=0');
		exit;
	}

	if (!count($errors) && filter_var($login, FILTER_VALIDATE_EMAIL))
	{
		if ($db->fetchColumn('select c1.value
			from contact c1, contact c2, type_contact tc
			where c1.id_type_contact = tc.id
				and c2.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c1.id <> c2.id
				and c1.value = c2.value'))
		{
			$errors[] = 'Je kan niet inloggen met email. Deze installatie bevat duplicate email adressen.';
		}
		else
		{
			$user = $db->fetchAssoc('select u.*
				from users u, contact c, type_contact tc
				where u.id = c.id_user
					and tc.id = c.id_type_contact
					and tc.abbrev = \'mail\'
					and c.value = ?', array($login));
		}
	}

	if (!$user && !count($errors))
	{
		if ($db->fetchColumn('select u1.letscode
			from users u1, users u2
			where u1.letscode = u2.letscode
				and u1.id <> u2.id
				and u1.letscode = ?', array($login)))
		{
			$errors[] = 'Je kan niet inloggen met je letscode. Deze installatie bevat duplicate letscodes.';
		}
		else
		{
			$user = $db->fetchAssoc('select * from users where letscode = ?', array($login));
		}
	}

	if (!$user && !count($errors))
	{
		if ($db->fetchColumn('select u1.name
			from users u1, users u2
			where u1.name = u2.name
				and u1.id <> u2.id
				and u1.name = ?', array($login)))
		{
			$errors[] = 'Je kan niet inloggen met je gebruikersnaam. Deze installatie bevat duplicate gebruikersnamen.';
		}
		else
		{
			$user = $db->fetchAssoc('select * from users where name = ?', array($login));
		}
	}

	if (!$user && !count($errors))
	{
		$errors[] = 'Login gefaald. Onbekende gebruiker.';
	}

	$sha512 = hash('sha512', $password);
	$sha1 = sha1($password);
	$md5 = md5($password);

	if (!count($errors) && in_array($user['password'], array($sha512, $sha1, $md5)))
	{
		if ($user['password'] != $sha512)
		{
			$db->update('users', array('password' => hash('sha512', $password)), array('id' => $user['id']));
		}

		if (!count($errors) && !in_array($user['status'], array(1, 2)))
		{
			$errors[] = 'Het account is niet actief.';
		}

		if (!count($errors) && !in_array($user['accountrole'], array('user', 'admin')))
		{
			$alert->error('Het account beschikt niet over de juiste rechten.');
		}

		if (!count($errors) && readconfigfromdb('maintenance') && $user['accountrole'] != 'admin')
		{
			$errors[] = 'De website is in onderhoud, probeer later opnieuw';
		}

		if (!count($errors))
		{
			$_SESSION = array(
				'id'			=> $user['id'],
				'name'			=> $user['name'],
				'fullname'		=> $user['fullname'],
				'login'			=> $user['login'],
				'postcode'		=> $user['postcode'],
				'letscode'		=> $user['letscode'],
				'accountrole'	=> $user['accountrole'],
				'userstatus'	=> $user['status'],
				'lang'			=> $user['lang'],
				'type'			=> 'local',
			);

			$browser = $_SERVER['HTTP_USER_AGENT'];
			log_event($user['id'],'Login','User ' .$user['login'] .' logged in');
			log_event($user['id'],'Agent', $browser);
			$db->update('users', array('lastlogin' => gmdate('Y-m-d H:i:s')), array('id' => $user['id']));
			readuser($user['id'], true);
			$alert->success('Je bent ingelogd.');
			$glue = (strpos($location, '?') === false) ? '?' : '&';
			header('Location: ' . $location . $glue . 'a=1&r=' . $user['accountrole'] . '&' . 'u=' .  $user['id']);
			exit;
		}
	}

	$alert->error(implode('<br>', $errors));
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

	echo aphp('pwreset', '', 'Ik ben mijn paswoord vergeten');
}

include $rootpath . 'includes/inc_footer.php';
