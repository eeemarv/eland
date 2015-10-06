<?php
ob_start();
$rootpath = './';
$role = 'anonymous';
$allow_anonymous_post = true;
require_once $rootpath . 'includes/inc_default.php';

if ($s_id)
{
	header('Location: ' . $rootpath . 'index.php');
	exit;
}

if ($token = $_GET['token'])
{
	$key = $schema . '_register_' . $token;

	if ($data = $redis->get($key))
	{
		$data = json_decode($data, true);
		$redis->del($key);

		$letscode = '--' . substr($token, 0, 5);

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

			if (!$db->fetchColumn('select name from users where name = ?', array($name)))
			{
				break;
			}
		}

		$user = array(
			'name'			=> $name,
			'fullname'		=> $data['first_name'] . ' ' . $data['last_name'],
			'postcode'		=> $data['postcode'],
			'letscode'		=> $letscode,
			'login'			=> $letscode,
			'minlimit'		=> readconfigfromdb('minlimit'),
			'maxlimit'		=> readconfigfromdb('maxlimit'),
			'status'		=> 5,
			'accountrole'	=> 'user',
			'cron_saldo'	=> 't',
			'lang'			=> 'nl',
			'hobbies'		=> '',
			'cdate'			=> gmdate('Y-m-d H:i:s'),
		);

		$db->beginTransaction();

		try
		{
			$db->insert('users', $user);
			$user_id = $db->lastInsertId('users_id_seq');

			$tc = array();

			$rs = $db->prepare('select abbrev, id from type_contact');

			$rs->execute();

			while($row = $rs->fetch())
			{
				$tc[$row['abbrev']] = $row['id'];
			}

			$mail = array(
				'id_user'			=> $user_id,
				'flag_public'		=> 0,
				'value'				=> $data['email'],
				'id_type_contact'	=> $tc['mail'],
			);
			$db->insert('contact', $mail);

			if ($data['gsm'] || $data['tel'])
			{
				if ($data['gsm'])
				{
					$gsm = array(
						'id_user'			=> $user_id,
						'flag_public'		=> 0,
						'value'				=> $data['gsm'],
						'id_type_contact'	=> $tc['gsm'],
					);
					$db->insert('contact', $gsm);
				}

				if ($data['tel'])
				{
					$tel = array(
						'id_user'			=> $user_id,
						'flag_public'		=> 0,
						'value'				=> $data['tel'],
						'id_type_contact'	=> $tc['tel'],
					);
					$db->insert('contact', $tel);
				}
			}
			$db->commit();
		}
		catch (Exception $e)
		{
			$db->rollback();
			throw $e;
		}

		$alert->success('Inschrijving voltooid.');

		require_once $rootpath . 'includes/inc_header.php';
/*
		echo '<div class="panel panel-success">';
		echo '<div class="panel-body">';

//		echo '<h2>Inschrijving gelukt</h2>';

		echo '</div>';
		echo '</div>';
*/
		require_once $rootpath . 'includes/inc_footer.php';
		exit;
	}

	$alert->error('Geen geldig token.');

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-danger">';
	echo '<div class="panel-heading">';

	echo '<h2>Registratie niet gelukt</h2>';

	echo '</div>';
	echo '<div class="panel-body">';

	echo '<a href="' . $rootpath . 'register.php" class="btn btn-default">Opnieuw proberen.</a>';

	echo '</div>';
	echo '</div>';

	require_once $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($_POST['zend'])
{
	$reg = array(
		'email'			=> $_POST['email'],
		'first_name'	=> $_POST['first_name'],
		'last_name'		=> $_POST['last_name'],
		'postcode'		=> $_POST['postcode'],
		'tel'			=> $_POST['tel'],
		'gsm'			=> $_POST['gsm'],
	);

	log_event($s_id, 'System', 'Registration request for ' . $reg['email']);

	if(!$reg['email'])
	{
		$alert->error('Vul een email adres in.');
	}
	else if (!filter_var($reg['email'], FILTER_VALIDATE_EMAIL))
	{
		$alert->error('Geen geldig email adres.');
	}
	else if ($db->fetchColumn('select c.id_user
		from contact c, type_contact tc
		where c. value = ?
			AND tc.id = c.id_type_contact
			AND tc.abbrev = \'mail\'', array($reg['email'])))
	{
		$alert->error('Er bestaat reeds een bevestigde inschrijving met dit mailadres.');
	}
	else if (!$reg['first_name'])
	{
		$alert->error('Vul een voornaam in.');
	}
	else if (!$reg['last_name'])
	{
		$alert->error('Vul een achternaam in.');
	}
	else if (!$reg['postcode'])
	{
		$alert->error('Vul een postcode in.');
	}
	else
	{
		$token = substr(hash('sha512', $schema . time() . $reg['email'] . $reg['first_name']), 0, 10);
		$key = $schema . '_register_' . $token;
		$redis->set($key, json_encode($reg));
		$redis->expire($key, 86400);
		$key = $schema . '_register_email_' . $email;
		$redis->set($key, '1');
		$redis->expire($key, 86400);
		$subject = '[' . readconfigfromdb('systemtag') . '] Bevestig je inschrijving';
		$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
		$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : ':' . $_SERVER['SERVER_PORT'];
		$url = $http . $_SERVER['SERVER_NAME'] . $port . '/register.php?token=' . $token;
		$message = 'Inschrijven voor ' . readconfigfromdb('systemname') . "\n\n";
		$message .= "Klik op deze link om je inschrijving  te bevestigen :\n\n" . $url . "\n\n";
		$message .= "Deze link blijft 1 dag geldig.\n\n";
		sendemail(readconfigfromdb('from_address'), $reg['email'], $subject, $message);
		$alert->warning('Open je mailbox en klik op de bevestigingslink in de email die we naar je verstuurd hebben om je inschrijving te voltooien.');
		log_event('', 'System', 'Bevestigings email verstuurd naar ' . $email);
		header('Location: ' . $rootpath . 'login.php');
		exit;
	}
}

$h1 = 'Inschrijven';
$fa = 'check-square-o';

require_once $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="first_name" class="col-sm-2 control-label">Voornaam*</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="first_name" name="first_name" ';
echo 'value="' . $reg['first_name'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="last_name" class="col-sm-2 control-label">Achternaam*</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="last_name" name="last_name" ';
echo 'value="' . $reg['last_name'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="email" class="col-sm-2 control-label">Email*</label>';
echo '<div class="col-sm-10">';
echo '<input type="email" class="form-control" id="email" name="email" ';
echo 'value="' . $reg['email'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="postcode" class="col-sm-2 control-label">Postcode*</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="postcode" name="postcode" ';
echo 'value="' . $reg['postcode'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="gsm" class="col-sm-2 control-label">Gsm</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="gsm" name="gsm" ';
echo 'value="' . $reg['gsm'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="tel" class="col-sm-2 control-label">Telefoon</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="tel" name="tel" ';
echo 'value="' . $reg['tel'] . '">';
echo '</div>';
echo '</div>';

echo '<input type="submit" class="btn btn-default" value="Inschrijven" name="zend">';
echo '</form>';

echo '</div>';
echo '</div>';

require_once $rootpath . 'includes/inc_footer.php';
exit;

$h1 = 'Login of paswoord vergeten';
