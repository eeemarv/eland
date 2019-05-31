<?php

if (isset($app['app_hoster_contact']))
{
	[$title, $link] = explode('@', $app['app_hoster_contact']);

	if (isset($_POST['zend']))
	{
		$mail = $_POST['mail'];
		$message = $_POST['message'];
		$browser = $_SERVER['HTTP_USER_AGENT'];
		$token = $_POST['token'];

		if (!$app['predis']->get('hosting_form_' . $token))
		{
			$errors[] = 'Het formulier is verlopen.';
		}

		if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
		{
			$errors[] = 'Geen geldig E-mail adres ingevuld.';
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
			$text = $message . "\r\n\r\n\r\n" . 'browser: ' . $browser . "\n" . 'token: ' . $token;

			$enc = getenv('SMTP_ENC') ?: 'tls';
			$transport = (new \Swift_SmtpTransport(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc))
				->setUsername(getenv('SMTP_USERNAME'))
				->setPassword(getenv('SMTP_PASSWORD'));
			$mailer = new \Swift_Mailer($transport);
			$mailer->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(100, 30));

			$message = (new \Swift_Message())
				->setSubject($title . ': Contact Formulier')
				->setBody($text)
				->setTo($to)
				->setFrom($from)
				->setReplyTo($mail);

			$mailer->send($message);

			header('Location: ' . $app['rootpath'] . '?form_ok=1');
		}
	}

	echo '<!DOCTYPE html>';
	echo '<html>';
	echo '<head>';
	echo '<title>Contact Formulier</title>';
	echo $app['assets']->get_css();
	echo '<meta http-equiv="Content-Type" ';
	echo 'content="text/html; charset=utf-8">';
	echo '<meta name="viewport" ';
	echo 'content="width=device-width, initial-scale=1">';
	echo '</head>';
	echo '<body>';

	echo '<div class="navbar navbar-default navbar-fixed-top">';
	echo '<div class="container-fluid">';

	echo '<div class="navbar-header">';

	echo '<a href="' . $link . '" ';
	echo 'class="navbar-brand">' . $title . '</a>';

	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="container-fluid">';
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	if (isset($_GET['form_ok']))
	{
		echo '<br><div class="panel panel-success">';
		echo '<div class="panel-heading">';
		echo '<h1>Uw bericht is verstuurd.</h1>';
		echo '<p>Er wordt spoedig contact met u opgenomen.</p>';
		echo '</div></div>';
		echo '<a href="' . $link . '" ';
		echo 'class="btn btn-default">';
		echo 'Terug naar de thuispagina</a>';
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

		echo '<h1>Contact Formulier</h1>';
		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';

		echo '<form method="post">';

		echo '<div class="form-group">';
		echo '<label for="mail" class="control-label">';
		echo 'E-mail*</label>';
		echo '<input type="email" class="form-control" ';
		echo 'id="mail" name="mail" ';
		echo 'value="';
		echo $mail ?? '';
		echo '" required>';
		echo '<p>Privacy: uw E-mail adres wordt voor ';
		echo 'geen ander doel gebruikt dan u terug te kunnen ';
		echo 'contacteren.</p>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="message" ';
		echo 'class="control-label">';
		echo 'Bericht</label>';
		echo '<textarea name="message" ';
		echo 'class="form-control" ';
		echo 'id="message" rows="6" required>';
		echo $message ?? '';
		echo '</textarea>';
		echo '</div>';

		echo '<input type="submit" name="zend" ';
		echo 'value="Verzenden" class="btn btn-info">';
		echo '<input type="hidden" name="token" ';
		echo 'value="';
		echo $token;
		echo '">';
		echo '</form>';

		echo '</div>';
		echo '</div>';

		echo '<br>';
	}
	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '<br><br><br><br><br><br>';
	echo '<footer class="footer">';

	echo '<p><a href="' . $link;
	echo '">';
	echo $title;
	echo '</a></p>';

	echo '</footer>';
	echo '</div>';
	echo '</body>';
	echo '</html>';
	exit;
}

include __DIR__ . '/../include/header.php';
include __DIR__ . '/../include/footer.php';
