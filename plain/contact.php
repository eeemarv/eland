<?php

if (!$app['s_anonymous'])
{
	exit;
}

if (!$app['config']->get('contact_form_en', $app['tschema']))
{
	$app['alert']->warning('De contactpagina is niet ingeschakeld.');
	$app['link']->redirect('login', $app['pp_ary'], []);
}

if($app['request']->isMethod('POST'))
{
	$email = strtolower($app['request']->request->get('email'));
	$message = $app['request']->request->get('message');

	if (empty($email) || !$email)
	{
		$errors[] = 'Vul je E-mail adres in';
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
	{
		$errors[] = 'Geen geldig E-mail adres';
	}

	if (empty($message) || strip_tags($message) == '' || !$message)
	{
		$errors[] = 'Geef een bericht in.';
	}

	if (!trim($app['config']->get('support', $app['tschema'])))
	{
		$errors[] = 'Het Support E-mail adres is niet ingesteld in dit Systeem';
	}

	if ($token_error = $app['form_token']->get_error())
	{
		$errors[] = $token_error;
	}

	if(!count($errors))
	{
		$contact = [
			'message' 	=> $message,
			'email'		=> $email,
			'agent'		=> $app['request']->headers->get('User-Agent'),
			'ip'		=> $app['request']->getClientIp(),
		];

		$token = $app['data_token']->store($contact,
			'contact', $app['tschema'], 86400);

		$app['monolog']->info('Contact form filled in with address ' .
			$email . ' ' .
			json_encode($contact),
			['schema' => $app['tschema']]);

		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'to' 		=> [$email => $email],
			'template'	=> 'contact/confirm',
			'vars'		=> ['token' => $token],
		], 10000);

		$app['alert']->success('Open je E-mailbox en klik
			de link aan die we je zonden om je
			bericht te bevestigen.');

		$app['link']->redirect('contact', $app['pp_ary'], []);
	}
	else
	{
		$app['alert']->error($errors);
	}
}
else
{
	$message = '';
	$email = '';
}

$form_disabled = false;

if (!$app['config']->get('mailenabled', $app['tschema']))
{
	$app['alert']->warning('E-mail functies zijn
		uitgeschakeld door de beheerder.
		Je kan dit formulier niet gebruiken');

	$form_disabled = true;
}
else if (!$app['config']->get('support', $app['tschema']))
{
	$app['alert']->warning('Er is geen support E-mail adres
		ingesteld door de beheerder.
		Je kan dit formulier niet gebruiken.');

	$form_disabled = true;
}

$app['heading']->add('Contact');
$app['heading']->fa('comment-o');

require_once __DIR__ . '/../include/header.php';

$top_text = $app['config']->get('contact_form_top_text', $app['tschema']);

if ($top_text)
{
	echo $top_text;
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post">';

echo '<div class="form-group">';
echo '<label for="mail">';
echo 'Je E-mail Adres';
echo '</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-envelope-o"></i>';
echo '</span>';
echo '<input type="email" class="form-control" id="email" name="email" ';
echo 'value="';
echo $email;
echo '" required';
echo $form_disabled ? ' disabled' : '';
echo '>';
echo '</div>';
echo '<p>';
echo 'Er wordt een validatielink die je moet ';
echo 'aanklikken naar je E-mailbox verstuurd.';
echo '</p>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="message">Je Bericht</label>';
echo '<textarea name="message" id="message" ';
echo $form_disabled ? 'disabled ' : '';
echo 'class="form-control" rows="4">';
echo $message;
echo '</textarea>';
echo '</div>';

echo '<input type="submit" name="zend" ';
echo $form_disabled ? 'disabled ' : '';
echo 'value="Verzenden" class="btn btn-default">';
echo $app['form_token']->get_hidden_input();

echo '</form>';

echo '</div>';
echo '</div>';

$bottom_text = $app['config']->get('contact_form_bottom_text', $app['tschema']);

if ($bottom_text)
{
	echo $bottom_text;
}

echo '<p>Leden: indien mogelijk, login en ';
echo 'gebruik het Support formulier. ';
echo '<i>Als je je paswoord kwijt bent ';
echo 'kan je altijd zelf een nieuw paswoord ';
echo 'aanvragen met je E-mail adres ';
echo 'vanuit de login-pagina!</i></p>';

include __DIR__ . '/../include/footer.php';
