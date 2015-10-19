<?php
ob_start();

$rootpath = './';
$role = 'anonymous';
$allow_anonymous_post = true;

require_once $rootpath . 'includes/inc_default.php';

if ($s_id)
{
	header('Location: index.php');
	exit;
}

$token = $_GET['token'];
$login = $_GET['login'];
$openid = $_GET['openid_identity'];
$location = $_GET['location'];
$location = ($location) ? urldecode($location) : 'index.php';
$location = ($location == 'login.php') ? 'index.php' : $location;
$location = ($location == 'logout.php') ? 'index.php' : $location;
$admin_modus = (strpos($location, '&admin=1') === false) ? false : true;
$location = str_replace('&admin=1', '', $location);
$error_location = 'login.php?location=' . urlencode($location);

if(!empty($token))
{
	$query = 'select token
		from tokens
		where token = ?
		and validity > ? 
		and type = \'guestlogin\'';

	if($db->fetchColumn($query, array($token, date('Y-m-d H:i:s'))))
	{
        session_start();
        $_SESSION['id'] = 0;
        $_SESSION['name'] = 'letsguest';
        $_SESSION['letscode'] = 'X000';
        $_SESSION['accountrole'] = 'guest';
        $_SESSION['rights'] = 'guest';
		$_SESSION['type'] = 'interlets';
		log_event($_SESSION['id'], 'Login', 'Guest login using token succeeded');
		$alert->success($_SESSION['name'] . ' ingelogd');
		header('Location: ' . $location);
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

	if (!($login && $password))
	{
		$alert->error('Login gefaald. Vul login en paswoord in.');
		header('Location: ' . $error_location);
		exit;
	}

	$master_password = getenv('ELAS_MASTER_PASSWORD');

	if ($login == 'master' && hash('sha512', $password) == $master_password)
	{
		session_start();
		$_SESSION['id'] = 0;
		$_SESSION['name'] = 'master';
		$_SESSION['fullname'] = 'eLAS Master';
		$_SESSION['login'] = 'master';
		$_SESSION['user_postcode'] = '0000';
		$_SESSION['letscode'] = '000000';
		$_SESSION['accountrole'] = 'admin';
		$_SESSION['rights'] = 'admin';
		$_SESSION['userstatus'] = 1;
		$_SESSION['email'] = '';
		$_SESSION['lang'] = 'nl';
		$_SESSION['type'] = 'master';
		log_event(0,'Login','Master user ' . $user['login'] . ' logged in');
		$alert->success('OK - Gebruiker ingelogd als master.');
		header('Location: ' . $location);
		exit;
	}

	$user = $db->fetchAssoc('SELECT * FROM users WHERE login = ?', array($login));
	if (!$user)
	{
		$alert->error('Login gefaald. Onbekende gebruiker.');
		header('Location: ' . $error_location);
		exit;
	}

	$sha512 = hash('sha512', $password);
	$sha1 = sha1($password);
	$md5 = md5($password);
	
	if (in_array($user['password'], array($sha512, $sha1, $md5)))
	{
		if ($user['password'] != $sha512)
		{
			$db->update('users', array('password' => hash('sha512', $password)), array('id' => $user['id']));
		}

		if ($user['status'] == 0)
		{
			$alert->error('Account is gedesactiveerd.');
			header('Location; ' . $error_location);
			exit;
		}

		if(readconfigfromdb('maintenance') && $user['accountrole'] != 'admin')
		{
			$alert->error('De website is in onderhoud, probeer later opnieuw');
			header('Location: ' . $error_location);
			exit;
		}

		$accountrole = $user['accountrole'];

		if ($accountrole == 'admin')
		{
			$accountrole = 'user';

			if ($admin_modus)
			{
				$accountrole = 'admin';
			}
		}

		session_start();
		$_SESSION['id'] = $user['id'];
		$_SESSION['name'] = $user['name'];
		$_SESSION['fullname'] = $user['fullname'];
		$_SESSION['login'] = $user['login'];
		$_SESSION['user_postcode'] = $user['postcode'];
		$_SESSION['letscode'] = $user['letscode'];
		$_SESSION['accountrole'] = $accountrole;
		$_SESSION['rights'] = $user['accountrole'];
		$_SESSION['userstatus'] = $user['status'];
		$_SESSION['email'] = $user['emailaddress'];
		$_SESSION['lang'] = $user['lang'];
		$_SESSION['type'] = 'local';

		$browser = $_SERVER['HTTP_USER_AGENT'];
		log_event($user['id'],'Login','User ' .$user['login'] .' logged in');
		log_event($user['id'],'Agent', $browser);
		$db->update('users', array('lastlogin' => gmdate('Y-m-d H:i:s')), array('id' => $s_id));
		$alert->success('Je bent ingelogd.');
		header('Location: ' . $location);
		exit;
	}

	$alert->error('Login gefaald.');
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
    echo '<label for="login" class="col-sm-2 control-label">Login</label>';
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

	echo '<a href="' . $rootpath . 'pwreset.php">Ik ben mijn paswoord en/of login vergeten.</a>';
}

include $rootpath . 'includes/inc_footer.php';

