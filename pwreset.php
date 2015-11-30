<?php
$rootpath = './';
$role = 'anonymous';
$allow_anonymous_post = true;
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_passwords.php';

if ($s_id)
{
	header('Location: ' . $rootpath . 'index.php');
	exit;
}

$token = $_GET['token'];

if ($token)
{
	if ($_POST['zend'])
	{
		$password = $_POST['password'];

		if (!(password_strength($password) < 50)) // ignored readconfigfromdb('pwscore')
		{
			if ($user_id = $redis->get($schema . '_token_' . $token))
			{
				$db->update('users', array('password' => hash('sha512', $password)), array('id' => $user_id));
				$user = readuser($user_id, true);
				$alert->success('Paswoord opgeslagen.');
				log_event($s_id, 'System', 'password reset success user ' . link_user($user, null, false, true));

				$to = array();
				$st = $db->prepare('SELECT c.value
					FROM contact c, type_contact tc
					WHERE tc.id = c.id_type_contact
						AND tc.abbrev = \'mail\'
						AND c.id_user = ?');
				$st->bindValue(1, $user_id);
				$st->execute();
				while ($row = $st->fetch())
				{
					$to[] = $row['value'];
				}

				$url = $base_url . '/login.php?login=' . $user['letscode'];

				$subj = '[' . $systemtag . '] nieuw paswoord.';
				$body = 'Beste ' . $user['name'] . ",\n\n";
				$body .= 'Er werd een nieuw paswoord voor je account ingesteld.';
				$body .= "\n\npaswoord: " . $password . "\n";
				$body .= 'login (letscode): ' . $user['letscode'] . "\n\n";
				$body .= 'Inloggen: ' . $url;

				sendemail(readconfigfromdb('from_address'), $to, $subj, $body);

				header('Location: ' . $rootpath . 'login.php');
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
	$fa = 'key';

	$includejs = '<script src="' . $rootpath . 'js/generate_password.js"></script>';

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<button class="btn btn-default" id="generate">Genereer automatisch</button>';
	echo '<br><br>';

	echo '<form method="post" class="form-horizontal">';
	echo '<div class="form-group">';
	echo '<label for="password" class="col-sm-2 control-label">Nieuw paswoord</label>';
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
	$email = $_POST['email'];

	if($email)
	{
		log_event($s_id, 'System', 'Activation request for ' . $email);
		$mail_ary = $db->fetchAll('SELECT c.id_user, u.letscode
			FROM contact c, type_contact tc, users u
			WHERE c. value = ?
				AND tc.id = c.id_type_contact
				AND tc.abbrev = \'mail\'
				AND c.id_user = u.id', array($email));

		if (count($mail_ary) < 2)
		{
			$user_id = $mail_ary[0]['id_user'];
			$letscode = $mail_ary[0]['letscode'];

			if ($user_id)
			{
				$token = substr(hash('sha512', $user_id . $schema . time() . $email), 0, 12);
				$key = $schema . '_token_' . $token;
				$redis->set($key, $user_id);
				$redis->expire($key, 3600);

				$url = $base_url . '/pwreset.php?token=' . $token;

				$subject = '[' . $systemtag . '] Paswoord reset link.';

				$message = "Link om je paswoord te resetten :\n\n" . $url . "\n\n";
				$message .= "Let op: deze link blijft slechts 1 uur geldig.\n\n";
				$message .= 'Je letscode is: ' . $letscode . "\n\n";
				$message .= 'Indien je niet zelf deze paswoord reset hebt aangevraagd op de website, ';
				$message .= 'gelieve deze mail te negeren.';

				sendemail(readconfigfromdb('from_address'), $email, $subject, $message);
				$alert->success('Een link om je paswoord te resetten werd naar je mailbox verzonden. Opgelet, deze link blijft slechts één uur geldig.');
				log_event($s_id, 'System', 'Paswoord reset link verstuurd naar ' . $email);
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
		$alert->error('Geef een mailadres op');
		log_event($s_id, 'System', 'Empty activation request');
	}
}

$h1 = 'Paswoord vergeten';

require_once $rootpath . 'includes/inc_header.php';

echo '<p>Met onderstaand formulier stuur je een link om je paswoord te resetten naar je mailbox. </p>';

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
