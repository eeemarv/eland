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

$locked = 0;

// Include the moologin javascript code
echo "<script type='text/javascript' src='$rootpath/js/moologin.js'></script>";

$token = $_GET["token"];
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
 
if(empty($token))
{
	echo "<div id='formdiv'>";
	echo "<form  method='post'>";
	echo "<table class='selectbox'><tr>";
	echo "<td>Login</td>";
	echo "<td><input type='text' name='login' size='30' value='" . $login . "'></td>";
	echo "</tr><tr>";
	echo "<td>Paswoord</td>";
	echo "<td><input type='password' name='password' size='30'></td>";
	echo "</tr>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' value='Inloggen' name='zend'>";
	echo "</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";
	
/*
	// Show the OpenID login box
	echo "<div id='openiddiv'>";
	echo "<form id='openidbox' name='openidbox' action='$rootpath/postopenid.php' method='post'>";
    echo "<table class='selectbox'><tr>";
    echo "<td><img src='$rootpath/gfx/openid.png'>OpenID</td>";
	echo "<td><input type='text' name='openid' size='50'></td>";
	echo "<input type='hidden' name='targeturl' value='" .$_GET['url'] ."'>";
	echo "<td><input type='submit' id='openidsubmitter' value=' OpenID inloggen'></td>";
    echo "</tr>";
    echo "<tr><td></td><td align='right'><small><i><a href='http://www.letsplaza.net/content/openid'>Wat is OpenID?</a></i></small></td></tr>";
	echo "</table>";
    echo "</form>";
    echo "</div>";

	// Focus the login field
	echo "<script type='text/javascript'>document.loginform.login.focus();</script>";

	// Draw the hidden password reset form
	echo "<div id='pwresetdiv' class='hidden'>";
	echo "<form id='pwresetform' name='pwresetform' action='$rootpath/pwreset.php' method='post'>";
        echo "<table class='selectbox' border='0'><tr>";
        echo "<td>E-mal adres</td>";
        echo "<td><input type='text' name='email' size='30'></td>";
        echo "</tr>";
	echo "<tr><td colspan='2' align='right'>";
        echo "<input type='submit' id='resetter' value='Reset paswoord'>";
        echo "</td></tr>";
	echo "</table>";
        echo "</form>";
        echo "</div>";

	// Draw the hidden guest login form
	echo "<div id='guestlogindiv' class='hidden'>";
        echo "<form id='guestloginform' name='guestloginform' action='' method='post'>";
	echo "</form>";
        echo "</div>";
        */

}

include($rootpath."includes/inc_footer.php");

