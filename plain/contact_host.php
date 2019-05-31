<?php

if ($app['request']->isMethod('POST'))
{
	$mail = $app['request']->request->get('mail');
	$message = $app['request']->request->get('message');

	$form_error = $app['form_token']->get_error();

	if ($form_error)
	{
		$errors[] = $form_error;
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
		$text = $message . "\r\n\r\n\r\n" . 'browser: ';
		$text .= $app['request']->headers->get('User-Agent') . "\n";
		$text .= 'form_token: ' . $app['form_token']->get();

		$enc = getenv('SMTP_ENC') ?: 'tls';
		$transport = (new \Swift_SmtpTransport(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc))
			->setUsername(getenv('SMTP_USERNAME'))
			->setPassword(getenv('SMTP_PASSWORD'));
		$mailer = new \Swift_Mailer($transport);
		$mailer->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(100, 30));

		$message = (new \Swift_Message())
			->setSubject('eLAND Contact Formulier')
			->setBody($text)
			->setTo($to)
			->setFrom($from)
			->setReplyTo($mail);

		$mailer->send($message);

		$app['link']->redirect('contact_host', [], ['form_ok' => 1]);
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

echo '<a href="https://eland.letsa.net" ';
echo 'class="navbar-brand">eLAND Contact Formulier</a>';

echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="container-fluid">';
echo '<div class="row">';
echo '<div class="col-md-12">';

if ($app['request']->query->get('form_ok') !== null)
{
	echo '<br><div class="panel panel-success">';
	echo '<div class="panel-heading">';
	echo '<h1>Uw bericht is verstuurd.</h1>';
	echo '<p>Er wordt spoedig contact met u opgenomen.</p>';
	echo '</div></div>';
	echo '<a href="https://eland.letsa.net" ';
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

	echo $app['form_token']->get_hidden_input();
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

echo '<p><a href="https://eland.letsa.net';
echo '">';
echo 'eLAND';
echo '</a></p>';

echo '</footer>';
echo '</div>';
echo '</body>';
echo '</html>';
