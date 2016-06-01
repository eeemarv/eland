<?php

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

		$subject = 'nieuwe inschrijving: ' . $user['fullname'];
		$text = '*** Dit is een automatische mail van ' . $systemtag . " *** \n\n";
		$text .= "De volgende persoon schreef zich in via de website: \n\n";
		$text .= 'Volledige naam: ' . $user['fullname'] . "\n";
		$text .= 'Postcode: ' . $user['postcode'] . "\n";
		$text .= 'Email: ' . $data['email'] . "\n\n";
		$text .= 'Link: ' . $base_url . '/users.php?id=' . $user_id . '&admin=1';

		mail_q(array('to' => 'admin', 'subject' => $subject, 'text' => $text));

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

	echo aphp('register', '', 'Opnieuw proberen', 'btn btn-default');

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

	log_event('system', 'Registration request for ' . $reg['email']);

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
		$alert->error('Er bestaat reeds een inschrijving met dit mailadres.');
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
	else if ($error_token = get_error_form_token())
	{
		$alert->error($error_token);
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
		$subject = '[' . $systemtag . '] Bevestig je inschrijving';
		$url = $base_url . '/register.php?token=' . $token;
		$text = 'Inschrijven voor ' . $systemname . "\n\n";
		$text .= "Klik op deze link om je inschrijving  te bevestigen :\n\n" . $url . "\n\n";
		$text .= "Deze link blijft 1 dag geldig.\n\n";

		mail_q(array('to' => $reg['email'], 'subject' => $subject, 'text' => $text));

		$alert->warning('Open je mailbox en klik op de bevestigingslink in de email die we naar je verstuurd hebben om je inschrijving te voltooien.');
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
generate_form_token();

echo '</form>';

echo '</div>';
echo '</div>';

require_once $rootpath . 'includes/inc_footer.php';
exit;
