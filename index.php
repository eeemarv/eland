<?php

$page_access = 'guest';
require_once __DIR__ . '/include/web.php';

if (isset($hosting_form))
{
	if (isset($_POST['zend']))
	{
		$mail = $_POST['mail'];
		$group_name = $_POST['group_name'];
		$message = $_POST['message'];
		$browser = $_SERVER['HTTP_USER_AGENT'];
		$token = $_POST['token'];

		if (!$app['predis']->get('hosting_form_' . $token))
		{
			$errors[] = 'Het formulier is verlopen.';
		}

		if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
		{
			$errors[] = 'Geen geldig mail adres ingevuld.';
		}

		if (!$group_name)
		{
			$errors[] = 'De naam van de letsgroep is niet ingevuld.';
		}

		if (!$message)
		{
			$errors[] = 'Het bericht is leeg.';
		}

		$to = getenv('MAIL_HOSTER_ADDRESS');
		$from = getenv('MAIL_FROM_ADDRESS');

		if (!$to || !$from)
		{
			$errors[] = 'Interne fout.';
		}

		if (!count($errors))
		{
			$subject = 'Aanvraag hosting: ' . $group_name;
			$text = $message . "\r\n\r\n\r\n" . 'browser: ' . $browser . "\n" . 'token: ' . $token;

			$enc = getenv('SMTP_ENC') ?: 'tls';
			$transport = Swift_SmtpTransport::newInstance(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc)
				->setUsername(getenv('SMTP_USERNAME'))
				->setPassword(getenv('SMTP_PASSWORD'));

			$mailer = Swift_Mailer::newInstance($transport);

			$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));

			$msg = Swift_Message::newInstance()
				->setSubject($subject)
				->setBody($text)
				->setTo($to)
				->setFrom($from)
				->setReplyTo($mail);

			$mailer->send($msg);

			header('Location: ' . $rootpath . '?form_ok=1');
		}
	}

	echo '<!DOCTYPE html>';
	echo '<html>';
	echo '<head>';
	echo '<title>eLAND hosting aanvraag</title>';
	echo $app['assets']->render_css();
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '</head>';
	echo '<body>';

	echo '<div class="navbar navbar-default navbar-fixed-top">';
	echo '<div class="container-fluid">';

	echo '<div class="navbar-header">';

	echo '<a href="http://letsa.net" class="navbar-brand">eLAND</a>';

	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="container-fluid">';
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	if ($_GET['form_ok'])
	{
		echo '<br><div class="panel panel-success">';
		echo '<div class="panel-heading">';
		echo '<h1>Uw aanvraag is verstuurd.</h1>';
		echo '<p>Er wordt spoedig contact met u opgenomen.</p>';
		echo '</div></div>';
		echo '<a href="http://letsa.net" class="btn btn-default">Naar de eLAND-documentatie</a>';
		echo '<br><br><br>';
	}
	else
	{
		if (count($errors))
		{
			echo '<br>';
			echo '<div class="panel panel-danger">';
			echo '<div class="panel-heading">';
			echo '<h3>Er deed zich een fout voor.</h3>';
			foreach ($errors as $error)
			{
				echo '<p>' . $error . '</p>';
			}
			echo '</div></div>';
			echo '<br>';

		}

		$token = sha1(microtime());
		$key = 'hosting_form_' . $token;
		$app['predis']->set($key, '1');
		$app['predis']->expire($key, 7200);

		echo '<h1>Aanvraag hosting eLAND</h1>';

		echo '<div class="panel panel-default">';
		echo '<div class="panel-heading">';

		echo '<form method="post" class="form-horizontal">';

		echo '<div class="form-group">';
		echo '<label for="subject" class="col-sm-2 control-label">Naam groep</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="text" class="form-control" name="group_name" ';
		echo 'value="' . $group_name . '" required>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="mail" class="col-sm-2 control-label">Email*</label>';
		echo '<div class="col-sm-10">';
		echo '<input type="email" class="form-control" id="mail" name="mail" ';
		echo 'value="' . $mail . '" required>';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="message" class="col-sm-2 control-label">Bericht</label>';
		echo '<div class="col-sm-10">';
		echo '<textarea name="message" class="form-control" id="message" rows="6" required>';
		echo $message;
		echo '</textarea>';
		echo '</div>';
		echo '</div>';

		echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
		echo '<input type="hidden" name="token" value="' . $token . '">';
		echo '</form>';

		echo '</div>';
		echo '</div>';

		echo '<br>';
		echo '<p>*Privacy: uw email adres wordt voor geen enkel ander doel gebruikt dan u terug te kunnen ';
		echo 'contacteren.</p>';

	}
	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '<br><br><br><br><br><br>';
	echo '<footer class="footer">';

	echo '<p><a href="http://letsa.net">eLAND';
	echo '</a>&nbsp; web app voor gemeenschapsmunten</p>';

	echo '</footer>';
	echo '</div>';
	echo '</body>';
	echo '</html>';
	exit;
}

/**
 *
 **/

include __DIR__ . '/include/header.php';
include __DIR__ . '/include/footer.php';
