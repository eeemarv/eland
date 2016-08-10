<?php

$rootpath = './';
$page_access = 'anonymous';

require_once $rootpath . 'includes/inc_default.php';

$token = $_GET['token'] ?? false;

if (!readconfigfromdb('contact_form_en'))
{
	$alert->warning('De contactpagina is niet ingeschakeld.');
	redirect_login();
}

if ($token)
{
	$key = $schema . '_contact_' . $token;
	$data = $app['redis']->get($key);

	if ($data)
	{
		$app['redis']->del($key);

		$data = json_decode($data, true);

		$html = $data['html'];

		mail_q([
			'subject'	=> 'kopie van je bericht naar ' . readconfigfromdb('systemname'),
			'html'		=> '<p>Dit bericht heb je verstuurd naar ' . readconfigfromdb('systemname') . '</p><hr>' . $html,
			'to'		=> $data['mail'],
		]);

		$html .= '<hr><p>Dit bericht werd ingegeven in het contactformulier van ';
		$html .= readconfigfromdb('systemname') . '. Het mailadres werd gevalideerd. ';
		$html .= 'Je kan reply kiezen om te reageren.</p>';
		$html .= '<ul>';
		$html .= '<li>mailadres: ' . $data['mail'] . '</li>';
		$html .= '<li>ip: ' . $data['ip'] . '</li>';
		$html .= '<li>browser: ' . $data['browser'] . '</li>';
		$html .= '</ul>';

		mail_q([
			'html'		=> $html,
			'to'		=> 'support',
			'subject'	=> 'Bericht van het contactformulier',
			'reply_to'	=> $data['mail'],
		]);

		$alert->success('Je bericht werd succesvol verzonden.');

		$success_text = readconfigfromdb('contact_form_success_text');

/*
		require_once $rootpath . 'includes/inc_header.php';

		if ($success_text)
		{
			echo $success_text;
		}

		require_once $rootpath . 'includes/inc_footer.php';
*/
		header('Location: ' . generate_url('contact'));
		exit;
	}

	$alert->error('Ongeldig of verlopen token.');
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

	if (!trim(readconfigfromdb('support')))
	{
		$errors[] = 'Het support mailadres is niet ingesteld op deze installatie';
	}

	if ($token_error = $app['eland.form_token']->get_error())
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

		$token = substr(hash('sha512', $schema . microtime()), 0, 10);
		$key = $schema . '_contact_' . $token;
		$app['redis']->set($key, json_encode($contact));
		$app['redis']->expire($key, 86400);

		log_event('contact', 'Contact form filled in with address ' . $mail . '(not confirmed yet) content: ' . $html);

		$link = $base_url . '/contact.php?token=' . $token;

		$html = '<p>Gelieve deze mail te negeren indien je niet zelf het ';
		$html .= '<a href="' . $base_url . '/contact.php">contactformulier van ';
		$html .= readconfigfromdb('systemname') . '</a> hebt ingevuld.</p>';

		$html .= '<p><a href="' . $link . '">Klik hier om je bericht in het contactformulier te bevestigen.</a></p>';

		$return_message =  mail_q([
			'to' 		=> $mail,
			'subject' 	=> 'Bevestig je bericht aan ' . readconfigfromdb('systemname'),
			'html' 		=> $html,
		]);

		if (!$return_message)
		{
			$alert->success('Open je mailbox en klik de link aan die we je zonden om je bericht te bevestigen.');
			header('Location: ' . generate_url('contact'));
			exit;
		}

		$alert->error('Mail niet verstuurd. ' . $return_message);
	}
	else
	{
		$alert->error($errors);
	}
}
else
{
	$description = '';
	$mail = '';
}

if (!readconfigfromdb('mailenabled'))
{
	$alert->warning('E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
}
else if (!readconfigfromdb('support'))
{
	$alert->warning('Er is geen support mailadres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
}

/*
$include_ary[] = 'summernote';
$include_ary[] = 'rich_edit.js';
*/

$app['eland.assets']->add(['summernote', 'rich_edit.js']);

$h1 = 'Contact';
$fa = 'comment-o';

require_once $rootpath . 'includes/inc_header.php';

$top_text = readconfigfromdb('contact_form_top_text');

if ($top_text)
{
	echo $top_text;
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

/*
echo '<div class="form-group">';
echo '<label for="letscode" class="col-sm-2 control-label">Letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="letscode" name="letscode" ';
echo 'value="' . $help['letscode'] . '" required>';
echo '</div>';
echo '</div>';
*/

echo '<div class="form-group">';
echo '<label for="mail" class="col-sm-2 control-label">Je mailadres</label>';
echo '<div class="col-sm-10">';
echo '<input type="email" class="form-control" id="mail" name="mail" ';
echo 'value="' . $mail . '" required>';
echo '<p><small>Er wordt een validatielink naar je gestuurd die je moet aanklikken.</small></p>';
echo '</div>';
echo '</div>';

/*
echo '<div class="form-group">';
echo '<label for="subject" class="col-sm-2 control-label">Onderwerp</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="subject" name="subject" ';
echo 'value="' . $help['subject'] . '" required>';
echo '</div>';
echo '</div>';
*/

echo '<div class="form-group">';
//echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
echo '<div class="col-sm-12">';
echo '<textarea name="description" class="form-control rich-edit" rows="4">';
echo $description;
echo '</textarea>';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
$app['eland.form_token']->generate();

echo '</form>';

echo '</div>';
echo '</div>';

$bottom_text = readconfigfromdb('contact_form_bottom_text');

if ($bottom_text)
{
	echo $bottom_text;
}

echo '<p><small>Leden: indien mogelijk, login en gebruik het supportformulier. ';
echo '<i>Als je je paswoord kwijt bent kan je altijd zelf een nieuw paswoord ';
echo 'aanvragen met je mailadres vanuit de login-pagina!</i></small></p>';

include $rootpath . 'includes/inc_footer.php';
