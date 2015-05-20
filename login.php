<?php
ob_start();
$ptitle="login";
$rootpath = "./";
$role = 'anonymous';
$allow_anonymous_post = true;

require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_tokens.php");
require_once($rootpath."includes/inc_auth.php");

//require_once($rootpath."contrib/includes/lightopenid/openid.php");

if ($s_id)
{
	header('Location: index.php');
	exit;
}

$token = $_GET["token"];
$login = $_GET["login"];
$openid = $_GET['openid_identity'];
$location = $_GET['location'];
$location = ($location) ? urldecode($location) : 'index.php';
$location = ($location == 'login.php') ? 'index.php' : $location;
$location = ($location == 'logout.php') ? 'index.php' : $location;
$error_location = 'login.php?location=' . urlencode($location);

// Intercept old direct links and rewrite them
if(!empty($redirectmsg)){
	$_GET['url'] = "http://$baseurl/messages/view.php?id=" .$redirectmsg;
}

// Verify the token first and redirect to index if it is valid
if(!empty($token)){
	if(verify_token($token, "guestlogin")){
        session_start();
        $_SESSION["id"] = 0;
        $_SESSION["name"] = "letsguest";
        $_SESSION["letscode"] = "X000";
        $_SESSION["accountrole"] = "guest";
		$_SESSION["type"] = "interlets";
		$_SESSION["status"] = array();
		log_event($_SESSION["id"],"Login","Guest login using token succeeded");
		$alert->success($_SESSION["name"] ." ingelogd");
		header('Location: ' . $location);
		exit;
	} else {
		$alert->error("Interlets login is mislukt.");
		log_event("","LogFail", "Token login failed ($token)");
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
		log_event(0,"Master","Login as master user");
		startmastersession();
		$alert->success("OK - Gebruiker ingelogd");
		header('Location: ' . $location);
		exit;
	}

	$user = $db->GetRow('SELECT * FROM users WHERE login = \'' . $login . '\'');
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
			$db->Execute('UPDATE users SET password = \'' . hash('sha512', $password) . '\' WHERE id = ' . $user['id']);
		}

		if ($user['status'] == 0)
		{
			$alert->error('Account is gedesactiveerd.');
			header('Location; ' . $error_location);
			exit;
		}

		if(readconfigfromdb("maintenance") == 1 && $user["accountrole"] != "admin")
		{
			$alert->error("eLAS is in onderhoud, probeer later opnieuw");
			header('Location: ' . $error_location);
			exit;
		}

		startsession($user);
		$alert->success('Ok Gebruiker ingelogd.');
		header('Location: ' . $location);
		exit;
	}

	$alert->error('Login gefaald.');
}

// OPENID login code goes here

//echo "<h1>" . $tr->get('login', 'login') ."</h1>";

if(!empty($_GET['url']))
{
	echo "<script type='text/javascript'>var redirecturl='" .$_GET['url'] ."';</script>";
} else {
	echo "<script type='text/javascript'>var redirecturl='index.php';</script>";
}

// Check if we are in maintenance mode
if(readconfigfromdb("maintenance") == 1)
{
	$alert->warning('eLAS is niet beschikbaar wegens onderhoudswerken.  Enkel admin gebruikers kunnen inloggen');
}

require_once($rootpath."includes/inc_header.php");

// Draw the login form division

echo '<h1>Login</h1>';
 
if(empty($token))
{
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

	echo "</form>";
	echo '<a href="' . $rootpath . 'pwreset.php">Ik ben mijn paswoord en/of login vergeten.</a>';
}

include($rootpath."includes/inc_footer.php");

