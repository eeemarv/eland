<?php

$page_access = 'anonymous';

require_once __DIR__ . '/include/web.php';

$tschema = $app['this_group']->get_schema();

$token = $_GET['token'] ?? false;

if (!$app['config']->get('contact_form_en', $tschema))
{
	$app['alert']->warning('De contactpagina is niet ingeschakeld.');
	redirect_login();
}

if ($token)
{
	$key = $tschema . '_contact_' . $token;
	$data = $app['predis']->get($key);

	if ($data)
	{
		$app['predis']->del($key);

		$data = json_decode($data, true);

		$ev_data = [
			'token'			=> $token,
			'script_name'	=> 'contact',
			'email'			=> $data['email'],
		];

		$app['xdb']->set('email_validated', $data['email'], $ev_data, $tschema);

		$vars = [
			'message'		=> $data['message'],
			'config_url'	=> $app['base_url'] . '/config.php?active_tab=mailaddresses',
			'ip'			=> $data['ip'],
			'browser'		=> $data['browser'],
			'email'			=> $data['email'],
			'group'			=> [
				'name' =>	$app['config']->get('systemname', $tschema),
				'tag' => 	$app['config']->get('systemtag', $tschema),
			],
		];

		$app['queue.mail']->queue([
			'schema'	=> $tschema,
			'template'	=> 'contact_copy',
			'vars'		=> $vars,
			'to'		=> [$data['email']],
		]);

		$app['queue.mail']->queue([
			'schema'	=> $tschema,
			'template'	=> 'contact',
			'vars'		=> $vars,
			'to'		=> $app['mail_addr_system']->get_support($tschema),
			'reply_to'	=> [$data['email']],
		]);

		$app['alert']->success('Je bericht werd succesvol verzonden.');

		$success_text = $app['config']->get('contact_form_success_text', $tschema);

		header('Location: ' . generate_url('contact'));
		exit;
	}

	$app['alert']->error('Ongeldig of verlopen token.');
}

if($post && isset($_POST['zend']))
{
	$email = trim(strtolower($_POST['email']));
	$message = trim($_POST['message']);

	$browser = $_SERVER['HTTP_USER_AGENT'];

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

	if (!trim($app['config']->get('support', $tschema)))
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
			'browser'	=> $browser,
			'ip'		=> $ip,
		];

		$token = substr(hash('sha512', $tschema . microtime()), 0, 10);
		$key = $tschema . '_contact_' . $token;
		$app['predis']->set($key, json_encode($contact));
		$app['predis']->expire($key, 86400);

		$app['monolog']->info('Contact form filled in with address ' .
			$email . '(not confirmed yet) content: ' .
			$html, ['schema' => $tschema]);

		$vars = [
			'group' => [
				'tag'	=> $app['config']->get('systemtag', $tschema),
				'name'	=> $app['config']->get('systemname', $tschema),
			],
			'contact_url'	=> $app['base_url'] . '/contact.php',
			'confirm_url'	=> $app['base_url'] . '/contact.php?token=' . $token,
		];

		$app['queue.mail']->queue([
			'schema'	=> $tschema,
			'to' 		=> [$email],
			'template'	=> 'contact_confirm',
			'vars'		=> $vars,
		]);

		$app['alert']->success('Open je E-mailbox en klik de link aan die we je zonden om je bericht te bevestigen.');
		header('Location: ' . generate_url('contact'));
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

if (!$app['config']->get('mailenabled', $tschema))
{
	$app['alert']->warning('E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
}
else if (!$app['config']->get('support', $tschema))
{
	$app['alert']->warning('Er is geen support E-mail adres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
}

$h1 = 'Contact';
$fa = 'comment-o';

require_once __DIR__ . '/include/header.php';

$top_text = $app['config']->get('contact_form_top_text', $tschema);

if ($top_text)
{
	echo $top_text;
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post">';

echo '<div class="form-group">';
echo '<label for="mail">Je E-mail Adres</label>';
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
echo 'Er wordt een validatielink die je moet aanklikken naar je E-mailbox verstuurd.';
echo '</p>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="message">Je Bericht</label>';
echo '<textarea name="message" id="message" class="form-control" rows="4">';
echo $message;
echo '</textarea>';
echo '</div>';

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
echo $app['form_token']->get_hidden_input();

echo '</form>';

echo '</div>';
echo '</div>';

$bottom_text = $app['config']->get('contact_form_bottom_text', $tschema);

if ($bottom_text)
{
	echo $bottom_text;
}

echo '<p>Leden: indien mogelijk, login en gebruik het Support formulier. ';
echo '<i>Als je je paswoord kwijt bent kan je altijd zelf een nieuw paswoord ';
echo 'aanvragen met je E-mail adres vanuit de login-pagina!</i></p>';

include __DIR__ . '/include/footer.php';
