<?php

$page_access = 'anonymous';

require_once __DIR__ . '/include/web.php';

$token = $_GET['token'] ?? false;

$tschema = $app['this_group']->get_schema();

if ($token)
{
	$data = $app['predis']->get($tschema . '_token_' . $token);
	$data = json_decode($data, true);

	$user_id = $data['user_id'];
	$email = $data['email'];

	if ($_POST['zend'])
	{
		$password = $_POST['password'];

		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
		}
		else if (!($app['password_strength']->get($password) < 50))
		{
			if ($user_id)
			{
				$app['db']->update($tschema . '.users', ['password' => hash('sha512', $password)], ['id' => $user_id]);
				$app['user_cache']->clear($user_id, $tschema);
				$user = $app['user_cache']->get($user_id, $tschema);
				$app['alert']->success('Paswoord opgeslagen.');

				$vars = [
					'group'		=> [
						'name'		=> $app['config']->get('systemname', $tschema),
						'tag'		=> $app['config']->get('systemtag', $tschema),
						'currency'	=> $app['config']->get('currency', $tschema),
						'support'	=> explode(',', $app['config']->get('support', $tschema)),
					],
					'password'	=> $password,
					'user'		=> $user,
					'url_login'	=> $app['base_url'] . '/login.php?login=' . $user['letscode'],
				];

				$app['queue.mail']->queue([
					'schema'	=> $tschema,
					'to' 		=> $app['mail_addr_user']->get($user_id, $tschema),
					'template'	=> 'password_reset',
					'vars'		=> $vars,
				]);

				header('Location: ' . $rootpath . 'login.php');
				exit;
			}

			$app['alert']->error('Het reset-token is niet meer geldig.');
			header('Location: pwreset.php');
			exit;
		}
		else
		{
			$app['alert']->error('Te zwak paswoord.');
		}
	}

	if ($email)
	{
		$ev_data = [
			'token'			=> $token,
			'user_id'		=> $user_id,
			'script_name'	=> 'pwreset',
			'email'			=> strtolower($email),
		];

		$app['xdb']->set('email_validated', $email, $ev_data, $tschema);
	}

	$h1 = 'Nieuw paswoord ingeven.';
	$fa = 'key';

	$app['assets']->add('generate_password.js');

	require_once __DIR__ . '/include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" role="form">';

	echo '<div class="form-group">';
	echo '<label for="password">Nieuw paswoord</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-key"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="password" name="password" ';
	echo 'value="';
	echo $password;
	echo '" required>';
	echo '<span class="input-group-btn">';
    echo '<button class="btn btn-default" type="button" id="generate">Genereer</button>';
    echo '</span>';
	echo '</div>';
	echo '</div>';

	echo '<input type="submit" class="btn btn-default" value="Bewaar paswoord" name="zend">';
	echo $app['form_token']->get_hidden_input();
	echo '</form>';

	echo '</div>';
	echo '</div>';

	require_once __DIR__ . '/include/footer.php';
	exit;
}

if (isset($_POST['zend']))
{
	$email = trim($_POST['email']);

	if ($error_token = $app['form_token']->get_error())
	{
		$app['alert']->error($error_token);
	}
	else if($email)
	{
		$user = $app['db']->fetchAll('select u.*
			from ' . $tschema . '.contact c, ' .
				$tschema . '.type_contact tc, ' .
				$tschema . '.users u
			where c. value = ?
				and tc.id = c.id_type_contact
				and tc.abbrev = \'mail\'
				and c.id_user = u.id
				and u.status in (1, 2)', [$email]);

		if (count($user) < 2)
		{
			$user = $user[0];

			if ($user['id'])
			{
				$token = substr(hash('sha512', $user['id'] . $tschema . time() . $email), 0, 12);
				$key = $tschema . '_token_' . $token;

				$app['predis']->set($key, json_encode(['user_id' => $user['id'], 'email' => $email]));
				$app['predis']->expire($key, 3600);

				$vars = [
					'group'		=> [
						'name'		=> $app['config']->get('systemname', $tschema),
						'tag'		=> $app['config']->get('systemtag', $tschema),
						'currency'	=> $app['config']->get('currency', $tschema),
						'support'	=> explode(',', $app['config']->get('support', $tschema)),
					],
					'token_url'	=> $app['base_url'] . '/pwreset.php?token=' . $token,
					'user'		=> $user,
					'url_login'	=> $app['base_url'] . '/login.php?login=' . $user['letscode'],
				];

				$app['queue.mail']->queue([
					'schema'	=> $tschema,
					'to' 		=> [$email],
					'template'	=> 'password_reset_confirm',
					'vars'		=> $vars,
				], 1000);

				$app['alert']->success('Een link om je paswoord te resetten werd naar je E-mailbox verzonden. Opgelet, deze link blijft slechts één uur geldig.');

				header('Location: login.php');
				exit;
			}
			else
			{
				$app['alert']->error('E-Mail adres niet bekend');
			}
		}
		else
		{
			$app['alert']->error('Het E-Mail adres niet uniek in dit Systeem.');
		}
	}
	else
	{
		$app['alert']->error('Geef een E-mail adres op');
	}
}

$h1 = 'Paswoord vergeten';

require_once __DIR__ . '/include/header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post">';

echo '<div class="form-group">';
echo '<label for="email" class="control-label">Je E-mail adres</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-envelope-o"></i>';
echo '</span>';
echo '<input type="email" class="form-control" id="email" name="email" ';
echo 'value="';
echo $email ?? '';
echo '" required>';
echo '</div>';
echo '<p>';
echo 'Vul hier het E-mail adres in waarmee je geregistreerd staat in het Systeem. ';
echo 'Een link om je paswoord te resetten wordt naar je E-mailbox verstuurd.';
echo '</p>';
echo '</div>';

echo '<input type="submit" class="btn btn-default" value="Reset paswoord" name="zend">';
echo $app['form_token']->get_hidden_input();
echo '</form>';

echo '</div>';
echo '</div>';

require_once __DIR__ . '/include/footer.php';
