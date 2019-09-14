<?php declare(strict_types=1);

if (!$app['pp_anonymous'])
{
	exit;
}

if (!$app['config']->get('registration_en', $app['pp_schema']))
{
	$app['alert']->warning('De inschrijvingspagina is niet ingeschakeld.');
	$app['link']->redirect('login', $app['pp_ary'], []);
}

if ($app['request']->isMethod('POST'))
{
	$reg = [
		'email'			=> $app['request']->request->get('email', ''),
		'first_name'	=> $app['request']->request->get('first_name', ''),
		'last_name'		=> $app['request']->request->get('last_name', ''),
		'postcode'		=> $app['request']->request->get('postcode', ''),
		'tel'			=> $app['request']->request->get('tel', ''),
		'gsm'			=> $app['request']->request->get('gsm', ''),
	];

	$app['monolog']->info('Registration request for ' .
		$reg['email'], ['schema' => $app['pp_schema']]);

	if(!$reg['email'])
	{
		$app['alert']->error('Vul een E-mail adres in.');
	}
	else if (!filter_var($reg['email'], FILTER_VALIDATE_EMAIL))
	{
		$app['alert']->error('Geen geldig E-mail adres.');
	}
	else if ($app['db']->fetchColumn('select c.id_user
		from ' . $app['pp_schema'] . '.contact c, ' .
			$app['pp_schema'] . '.type_contact tc
		where c. value = ?
			AND tc.id = c.id_type_contact
			AND tc.abbrev = \'mail\'', [$reg['email']]))
	{
		$app['alert']->error('Er bestaat reeds een inschrijving
			met dit E-mail adres.');
	}
	else if (!$reg['first_name'])
	{
		$app['alert']->error('Vul een Voornaam in.');
	}
	else if (!$reg['last_name'])
	{
		$app['alert']->error('Vul een Achternaam in.');
	}
	else if (!$reg['postcode'])
	{
		$app['alert']->error('Vul een Postcode in.');
	}
	else if ($error_token = $app['form_token']->get_error())
	{
		$app['alert']->error($error_token);
	}
	else
	{
		$token = $app['data_token']->store($reg,
			'register', $app['pp_schema'], 604800); // 1 week

		$app['queue.mail']->queue([
			'schema'	=> $app['pp_schema'],
			'to' 		=> [$reg['email'] => $reg['first_name'] . ' ' . $reg['last_name']],
			'vars'		=> ['token' => $token],
			'template'	=> 'register/confirm',
		], 10000);

		$app['alert']->success('Open je E-mailbox en klik op de
			bevestigingslink in de E-mail die we naar je gestuurd
			hebben om je inschrijving te voltooien.');

		$app['link']->redirect('login', $app['pp_ary'], []);
	}
}

$app['heading']->add('Inschrijven');
$app['heading']->fa('check-square-o');

require_once __DIR__ . '/../include/header.php';

$top_text = $app['config']->get('registration_top_text', $app['pp_schema']);

if ($top_text)
{
	echo $top_text;
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post">';

echo '<div class="form-group">';
echo '<label for="first_name" class="control-label">Voornaam*</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-user"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="first_name" name="first_name" ';
echo 'value="';
echo $reg['first_name'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="last_name" class="control-label">Achternaam*</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-user"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="last_name" name="last_name" ';
echo 'value="';
echo $reg['last_name'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="email" class="control-label">E-mail*</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-envelope-o"></i>';
echo '</span>';
echo '<input type="email" class="form-control" id="email" name="email" ';
echo 'value="';
echo $reg['email'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="postcode" class="control-label">Postcode*</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-map-marker"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="postcode" name="postcode" ';
echo 'value="';
echo $reg['postcode'] ?? '';
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="gsm" class="control-label">Gsm</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-mobile"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="gsm" name="gsm" ';
echo 'value="';
echo $reg['gsm'] ?? '';
echo  '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="tel" class="control-label">Telefoon</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-phone"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="tel" name="tel" ';
echo 'value="';
echo $reg['tel'] ?? '';
echo '">';
echo '</div>';
echo '</div>';

echo '<input type="submit" class="btn btn-default" value="Inschrijven" name="zend">';
echo $app['form_token']->get_hidden_input();

echo '</form>';

echo '</div>';
echo '</div>';

$bottom_text = $app['config']->get('registration_bottom_text', $app['pp_schema']);

if ($bottom_text)
{
	echo $bottom_text;
}

require_once __DIR__ . '/../include/footer.php';
