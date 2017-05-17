<?php

$page_access = 'anonymous';

require_once __DIR__ . '/include/web.php';

$token = $_GET['token'] ?? false;

if (!$app['config']->get('contact_form_en'))
{
	$app['alert']->warning('De contactpagina is niet ingeschakeld.');
	redirect_login();
}

if ($token)
{
	$key = $app['this_group']->get_schema() . '_contact_' . $token;
	$data = $app['predis']->get($key);

	if ($data)
	{
		$app['predis']->del($key);

		$data = json_decode($data, true);

		$ev_data = [
			'token'			=> $token,
			'script_name'	=> 'contact',
			'email'			=> strtolower($data['mail']),
		];

		$app['xdb']->set('email_validated', $data['mail'], $ev_data);

		$msg_html = $data['html'];

		$converter = new \League\HTMLToMarkdown\HtmlConverter();
		$converter_config = $converter->getConfig();
		$converter_config->setOption('strip_tags', true);
		$converter_config->setOption('remove_nodes', 'img');

		$msg_text = $converter->convert($msg_html);

		$vars = [
			'msg_html'		=> $msg_html,
			'msg_text'		=> $msg_text,
			'config_url'	=> $app['base_url'] . '/config.php?active_tab=mailaddresses',
			'ip'			=> $data['ip'],
			'browser'		=> $data['browser'],
			'mail'			=> $data['mail'],
			'group'			=> [
				'name' =>	$app['config']->get('systemname'),
				'tag' => 	$app['config']->get('systemtag'),
			],
		];

		$app['queue.mail']->queue([
			'template'	=> 'contact_copy',
			'vars'		=> $vars,
			'to'		=> $data['mail'],
		]);

		$app['queue.mail']->queue([
			'template'	=> 'contact',
			'vars'		=> $vars,
			'to'		=> 'support',
			'reply_to'	=> $data['mail'],
		]);

		$app['alert']->success('Je bericht werd succesvol verzonden.');

		$success_text = $app['config']->get('contact_form_success_text');

		header('Location: ' . generate_url('contact'));
		exit;
	}

	$app['alert']->error('Ongeldig of verlopen token.');
}

if($post && isset($_POST['zend']))
{
	$mail = isset($_POST['mail']) ? trim($_POST['mail']) : false;
	$description = isset($_POST['description']) ? trim($_POST['description']) : false;

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

	if (empty($mail) || !$mail)
	{
		$errors[] = 'Vul je mailadres in';
	}

	if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
	{
		$errors[] = 'Geen geldig mailadres';
	}

	if (empty($description) || strip_tags($description) == '')
	{
		$errors[] = 'Geef een bericht in.';
	}

	if (!trim($app['config']->get('support')))
	{
		$errors[] = 'Het support mailadres is niet ingesteld op deze installatie';
	}

	if ($token_error = $app['form_token']->get_error())
	{
		$errors[] = $token_error;
	}

	if(!count($errors))
	{
		$config_htmlpurifier = HTMLPurifier_Config::createDefault();
		$config_htmlpurifier->set('Cache.DefinitionImpl', null);
		$htmlpurifier = new HTMLPurifier($config_htmlpurifier);
		$html = $htmlpurifier->purify($description);

		$contact = [
			'html' 		=> $html,
			'mail'		=> $mail,
			'browser'	=> $browser,
			'ip'		=> $ip,
		];

		$token = substr(hash('sha512', $app['this_group']->get_schema() . microtime()), 0, 10);
		$key = $app['this_group']->get_schema() . '_contact_' . $token;
		$app['predis']->set($key, json_encode($contact));
		$app['predis']->expire($key, 86400);

		$app['monolog']->info('Contact form filled in with address ' . $mail . '(not confirmed yet) content: ' . $html);

		$vars = [
			'group' => [
				'tag'	=> $app['config']->get('systemtag'),
				'name'	=> $app['config']->get('systemname'),
			],
			'contact_url'	=> $app['base_url'] . '/contact.php',
			'confirm_url'	=> $app['base_url'] . '/contact.php?token=' . $token,
		];

		$return_message =  $app['queue.mail']->queue([
			'to' 		=> $mail,
			'template'	=> 'contact_confirm',
			'vars'		=> $vars,
		]);

		if (!$return_message)
		{
			$app['alert']->success('Open je mailbox en klik de link aan die we je zonden om je bericht te bevestigen.');
			header('Location: ' . generate_url('contact'));
			exit;
		}

		$app['alert']->error('Mail niet verstuurd. ' . $return_message);
	}
	else
	{
		$app['alert']->error($errors);
	}
}
else
{
	$description = '';
	$mail = '';
}

if (!$app['config']->get('mailenabled'))
{
	$app['alert']->warning('E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
}
else if (!$app['config']->get('support'))
{
	$app['alert']->warning('Er is geen support mailadres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
}

$app['assets']->add(['summernote', 'rich_edit.js']);

$h1 = 'Contact';
$fa = 'comment-o';

require_once __DIR__ . '/include/header.php';

$top_text = $app['config']->get('contact_form_top_text');

if ($top_text)
{
	echo $top_text;
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="mail" class="col-sm-2 control-label">Je mailadres</label>';
echo '<div class="col-sm-10">';
echo '<input type="email" class="form-control" id="mail" name="mail" ';
echo 'value="' . $mail . '" required>';
echo '<p><small>Er wordt een validatielink naar je gestuurd die je moet aanklikken.</small></p>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<div class="col-sm-12">';
echo '<textarea name="description" class="form-control rich-edit" rows="4">';
echo $description;
echo '</textarea>';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
$app['form_token']->generate();

echo '</form>';

echo '</div>';
echo '</div>';

$bottom_text = $app['config']->get('contact_form_bottom_text');

if ($bottom_text)
{
	echo $bottom_text;
}

echo '<p><small>Leden: indien mogelijk, login en gebruik het supportformulier. ';
echo '<i>Als je je paswoord kwijt bent kan je altijd zelf een nieuw paswoord ';
echo 'aanvragen met je mailadres vanuit de login-pagina!</i></small></p>';

include __DIR__ . '/include/footer.php';
