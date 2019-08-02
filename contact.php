<?php

use Gregwar\Captcha\CaptchaBuilder;

$page_access = 'anonymous';

require_once __DIR__ . '/include/web.php';

$token = $_GET['token'] ?? false;

if (!$app['config']->get('contact_form_en', $app['tschema']))
{
	$app['alert']->warning('De contactpagina is niet ingeschakeld.');
	redirect_login();
}

if ($token)
{
	$key = $app['tschema'] . '_contact_' . $token;
	$data = $app['predis']->get($key);

	if ($data)
	{
		$app['predis']->del($key);

		$data = json_decode($data, true);

		$vars = [
			'message'		=> $data['message'],
			'ip'			=> $data['ip'],
			'agent'			=> $data['agent'],
			'email'			=> $data['email'],
		];

		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'template'	=> 'contact/copy',
			'vars'		=> $vars,
			'to'		=> [$data['email'] => $data['email']],
		], 9000);

		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'template'	=> 'contact/support',
			'vars'		=> $vars,
			'to'		=> $app['mail_addr_system']->get_support($app['tschema']),
			'reply_to'	=> [$data['email']],
		], 8000);

		$app['alert']->success('Je bericht werd succesvol verzonden.');

		$success_text = $app['config']->get('contact_form_success_text', $app['tschema']);

		header('Location: ' . generate_url('contact', []));
		exit;
	}

	$app['alert']->error('Ongeldig of verlopen token.');
}

if($app['is_http_post'] && isset($_POST['zend']))
{
	$email = trim(strtolower($_POST['email']));
	$message = trim($_POST['message']);

	$agent = $_SERVER['HTTP_USER_AGENT'];

	if (isset($_SERVER['HTTP_CLIENT_IP']))
	{
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	}
	else if (isset($_SERVER['HTTP_X_FORWARDE‌​D_FOR']))
	{
		$ip = $_SERVER['HTTP_X_FORWARDE‌​D_FOR'];
	}
	else
	{
		$ip = $_SERVER['REMOTE_ADDR'];
	}

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

	$captcha_key = 'captcha_';
	$captcha_key .= $app['form_token']->get_posted();
	$captcha_key .= '_';
	$captcha_key .= '_' . $_POST['captcha'];

	if(!$app['predis']->get($captcha_key))
	{
		$errors[] = 'De verificatiecode werd niet correct ingevuld.';
	}

	if(!count($errors))
	{
		$contact = [
			'message' 	=> $message,
			'email'		=> $email,
			'agent'		=> $agent,
			'ip'		=> $ip,
		];

		$token = substr(hash('sha512', $app['tschema'] . microtime()), 0, 10);
		$key = $app['tschema'] . '_contact_' . $token;
		$app['predis']->set($key, json_encode($contact));
		$app['predis']->expire($key, 86400);

		$app['monolog']->info('Contact form filled in with address ' .
			$email . ' ' .
			json_encode($contact),
			['schema' => $app['tschema']]);

		$vars = [
			'token'			=> $token,
		];

		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'to' 		=> [$email => $email],
			'template'	=> 'contact/confirm',
			'vars'		=> $vars,
		], 10000);

		$app['alert']->success('Open je E-mailbox en klik
			de link aan die we je zonden om je
			bericht te bevestigen.');

		header('Location: ' . generate_url('contact', []));
		exit;
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

$captcha_inline = '';

if (!$app['config']->get('mailenabled', $app['tschema']))
{
	$app['alert']->warning('E-mail functies zijn
		uitgeschakeld door de beheerder.
		Je kan dit formulier niet gebruiken');
}
else if (!$app['config']->get('support', $app['tschema']))
{
	$app['alert']->warning('Er is geen support E-mail adres
		ingesteld door de beheerder.
		Je kan dit formulier niet gebruiken.');
}
else
{
	$captcha = new CaptchaBuilder;
	$captcha->setDistortion(false);
	$captcha->setIgnoreAllEffects(true);
	$captcha->build();
	$captcha_inline = $captcha->inline();
	$captcha_phrase = $captcha->getPhrase();
	$captcha_key = 'captcha_';
	$captcha_key .= $app['form_token']->get();
	$captcha_key .= '_';
	$captcha_key .= '_' . $captcha->getPhrase();
	$app['predis']->set($captcha_key, '1');
	$app['predis']->expire($captcha_key, 14400);
}

$h1 = 'Contact';
$fa = 'comment-o';

require_once __DIR__ . '/include/header.php';

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
echo '" required>';
echo '</div>';
echo '<p>';
echo 'Er wordt een validatielink die je moet ';
echo 'aanklikken naar je E-mailbox verstuurd.';
echo '</p>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="message">Je Bericht</label>';
echo '<textarea name="message" id="message" ';
echo 'class="form-control" rows="4">';
echo $message;
echo '</textarea>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="captcha">';
echo 'Anti-spam verificatiecode';
echo '</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-code"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="captcha" name="captcha" ';
echo 'value="" required>';
echo '</div>';
echo '<p>';
echo 'Type de code in die hieronder getoond wordt.';
echo '</p>';
echo '<img src="';
echo $captcha_inline;
echo '" alt="Captcha niet geladen.">';
echo '</div>';

echo '<input type="submit" name="zend" ';
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

include __DIR__ . '/include/footer.php';
