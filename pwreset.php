<?php
ob_start();
$rootpath = './';
$role = 'anonymous';
$allow_anonymous_post = true;
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_passwords.php';
require_once $rootpath . 'includes/inc_mailfunctions.php';

if ($s_id)
{
	header('Location: ' . $rootpath . 'index.php');
	exit;
}

$user_id = $_GET['u'];
$token = $_GET['token'];

if ($token & $user_id)
{
	if ($_POST['zend'])
	{
		$password = $_POST['password'];

		if (!(password_strength($password) < readconfigfromdb('pwscore')))
		{
			if ($db->fetchColumn('select token
				from tokens
					where token = ?
						and validity > ?
						and type = \'pwreset\'', 
				array($token . '_' . $user_id, gmdate('Y-m-d H:i:s'))))
			{
				$db->update('users', array('password' => hash('sha512', $password)), array('id' => $user_id));
				readuser($user_id, true);
				$alert->success('Paswoord opgeslagen.');
				log_event($s_id, 'System', 'password reset success user ' . $user_id);
				header('Location: login.php');
				exit;
			}
			$alert->error('Het reset-token is niet meer geldig.');
			header('Location: pwreset.php');
			exit;
		}
		else
		{
			$alert->error('Te zwak paswoord.');
		}
	}

	$h1 = 'Nieuw paswoord ingeven.';

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	
	echo '<form method="post" class="form-horizontal">';
	echo '<div class="form-group">';
	echo '<label for="email" class="col-sm-2 control-label">Nieuw paswoord</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="password" name="password" ';
	echo 'value="' . $password . '" required>';
	echo '</div>';
	echo '</div>';
	echo '<input type="submit" class="btn btn-default" value="Reset paswoord" name="zend">';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	require_once $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($_POST['zend'])
{
	$email = $_POST["email"];
	if($email)
	{
		log_event($s_id,"System","Activation request for " .$email);
		$mail_ary = $db->fetchAll('SELECT c.id_user, u.login
			FROM contact c, type_contact tc, users u
			WHERE c. value = ?
				AND tc.id = c.id_type_contact
				AND tc.abbrev = \'mail\'
				AND c.id_user = u.id', array($email));

		if (count($mail_ary) < 2)
		{
			$user_id = $mail_ary[0]['id_user'];
			$login = $mail_ary[0]['login'];

			if ($user_id)
			{
				$token = substr(hash('sha512', $user_id . $schema . time() . $email), 0, 10);
				$validity = gmdate('Y-m-d H:i:s', time() + 3600);
				$db->insert('tokens', array(
					'token'		=> $token . '_' . $user_id,
					'validity' 	=> $validity,
					'type'		=> 'pwreset'));
				$subject = '[eLAS-' . readconfigfromdb('systemtag') . '] Paswoord reset link.';
				$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
				$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : ':' . $_SERVER['SERVER_PORT'];
				$url = $http . $_SERVER["SERVER_NAME"] . $port . '/pwreset.php?token=' . $token . '&u=' . $user_id;
				$message = "Link om je paswoord te resetten :\n\n" . $url . "\n\n";
				$message .= "Let op: deze link blijft slechts 1 uur geldig.\n\n";
				$message .= "Je login is: ". $login;
				sendemail(readconfigfromdb('from_address'), $email, $subject, $message);
				$alert->success('Een link om je paswoord te resetten werd naar je mailbox verzonden. Opgelet, deze link blijft slechts één uur geldig.');
				log_event($s_id,"System","Paswoord reset link verstuurd naar " . $email);
				header('Location: login.php');
				exit;
			}
			else
			{
				$alert->error('Mailadres niet bekend');
			}
		}
		else
		{
			$alert->error('Mailadres niet uniek.');
		}
	}
	else
	{
		$alert->error("Geef een mailadres op");
		log_event($s_id,"System","Empty activation request");
	}
}

$h1 = 'Login of paswoord vergeten';

require_once $rootpath . 'includes/inc_header.php';

echo '<p>Met onderstaand formulier stuur je je login en een link om je paswoord te resetten naar je mailbox. </p>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';
echo '<div class="form-group">';
echo '<label for="email" class="col-sm-2 control-label">Email</label>';
echo '<div class="col-sm-10">';
echo '<input type="email" class="form-control" id="email" name="email" ';
echo 'value="' . $email . '" required>';
echo '</div>';
echo '</div>';
echo '<input type="submit" class="btn btn-default" value="Reset paswoord" name="zend">';
echo '</form>';

echo '</div>';
echo '</div>';

require_once $rootpath . 'includes/inc_footer.php';
