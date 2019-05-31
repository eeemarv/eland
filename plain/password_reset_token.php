<?php

if (!$app['s_anonymous'])
{
	exit;
}

$token = $app['request']->query->get('token');

if (!$token)
{
	exit;
}

$data = $app['predis']->get($app['tschema'] . '_token_' . $token);
$data = json_decode($data, true);

$user_id = $data['user_id'];
$email = $data['email'];

if ($app['request']->isMethod('POST'))
{
	$password = $app['request']->request->get('password');

	if ($error_token = $app['form_token']->get_error())
	{
		$app['alert']->error($error_token);
	}
	else if (!($app['password_strength']->get($password) < 50))
	{
		if ($user_id)
		{
			$app['db']->update($app['tschema'] . '.users',
				['password' => hash('sha512', $password)],
				['id' => $user_id]);
			$app['user_cache']->clear($user_id, $app['tschema']);
			$app['alert']->success('Paswoord opgeslagen.');

			$app['queue.mail']->queue([
				'schema'	=> $app['tschema'],
				'to' 		=> $app['mail_addr_user']->get($user_id, $app['tschema']),
				'template'	=> 'password_reset/user',
				'vars'		=> [
					'password'		=> $password,
					'user_id'		=> $user_id,
				],
			], 10000);

			$app['link']->redirect('login', $app['pp_ary'], []);
		}

		$app['alert']->error('Het reset-token is niet meer geldig.');

		$app['link']->redirect('password_reset', $app['pp_ary'], []);
	}
	else
	{
		$app['alert']->error('Het paswoord is te zwak.');
	}
}

$app['heading']->add('Nieuw paswoord ingeven.');
$app['heading']->fa('key');

$app['assets']->add([
	'generate_password.js',
]);

require_once __DIR__ . '/../include/header.php';

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

require_once __DIR__ . '/../include/footer.php';
