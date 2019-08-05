<?php declare(strict_types=1);

if (!$app['s_anonymous'])
{
	exit;
}

if ($app['request']->isMethod('POST'))
{
	$email = $app['request']->request->get('email');

	if ($error_token = $app['form_token']->get_error())
	{
		$app['alert']->error($error_token);
	}
	else if($email)
	{
		$user = $app['db']->fetchAll('select u.id, u.name, u.letscode
			from ' . $app['tschema'] . '.contact c, ' .
				$app['tschema'] . '.type_contact tc, ' .
				$app['tschema'] . '.users u
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
				$user_id = $user['id'];

				$token = $app['data_token']->store([
					'user_id'	=> $user_id,
					'email'		=> $email,
				], 'password_reset', $app['tschema'], 86400);

				$app['queue.mail']->queue([
					'schema'	=> $app['tschema'],
					'to' 		=> [$email => $user['letscode'] . ' ' . $user['name']],
					'template'	=> 'password_reset/confirm',
					'vars'		=> [
						'token'			=> $token,
						'user_id'		=> $user_id,
						'pp_ary'		=> $app['pp_ary'],
					],
				], 10000);

				$app['alert']->success('Een link om je paswoord te resetten werd
					naar je E-mailbox verzonden. Deze link blijft 24 uur geldig.');

				$app['link']->redirect('login', $app['pp_ary'], []);
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

$app['heading']->add('Paswoord vergeten');
$app['heading']->fa('key');

require_once __DIR__ . '/../include/header.php';

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

require_once __DIR__ . '/../include/footer.php';
