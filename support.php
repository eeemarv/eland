<?php

$rootpath = './';
$page_access = 'user';

require_once $rootpath . 'includes/inc_default.php';

if(isset($_POST['zend']))
{
	$help = [
		'subject' 			=> $_POST['subject'],
		'description' 		=> $_POST['description'],
	];

	if(empty($help['subject']))
	{
        $errors[] = 'Geef een onderwerp op.';
	}

	if(empty($help['description']))
	{
		$errors[] = 'Geef een omschrijving van je probleem.';
	}

	if (!trim(readconfigfromdb('support')))
	{
		$errors[] = 'Het support email adres is niet ingesteld op deze installatie';
	}

	if ($s_master)
	{
		$errors[] = 'Het master account kan geen berichten versturen.';
	}

	if ($token_error = get_error_form_token())
	{
		$errors[] = $token_error;
	}

	$my_contacts = $db->fetchAll('select c.value, tc.abbrev
		from contact c, type_contact tc
		where c.id_user = ?
			and c.id_type_contact = tc.id', [$s_id]);

	$mailaddr = getmailadr($s_id);

	$mail_ary = ['to' => 'support', 'subject' => $help['subject']];

	foreach ($my_contacts as $my_contact)
	{
		if ($my_contact['abbrev'] == 'mail')
		{
			$mail_ary['reply_to'] = $s_id;
		}
	}

	if(!count($errors))
	{
		$text  = "-- via de website werd het volgende probleem gemeld --\r\n";
		$text .= 'E-mail: ' . $help['mail'] . "\r\n";

		$text .= 'Gebruiker: ' . link_user($s_id, false, false) . "\r\n";

		$text .= "\r\n\r\n";
		$text .= "------------------------------ Bericht -----------------------------\r\n\r\n";
		$text .= $help['description'] . "\r\n\r\n";
		$text .= "--------------------------------------------------------------------\r\n\r\n";

		if ($mail_ary['reply_to'])
		{
			$text .= 'Om te antwoorden kan je gewoon reply kiezen of de contactgegevens hieronder gebruiken';
		}
		else
		{
			$text .= 'Er is geen mailadres gekend van ' . link_user($s_id, false, false);
			$text .= '. Contacteer de gebruiker op andere wijze.';
		}

		$text .= 'Contactgegevens van ' . link_user($s_id, false, false) . ":\r\n\r\n";

		foreach($my_contacts as $value)
		{
			$text .= '* ' . $value['abbrev'] . "\t" . $value['value'] ."\n";
		}

		$mail_ary['text'] = $text;

		$return_message =  mail_q($mail_ary);

		if (!$return_message)
		{
			$alert->success('De support mail is verzonden.');
			header('Location: ' . generate_url('index'));
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
	$help = [
		'subject' 			=> '',
		'description' 		=> '',
	];

	if ($s_master)
	{
		$alert->warning('Het master account kan geen berichten versturen.');
	}
	else
	{
		$mail = getmailadr($s_id);

		if (!count($mail))
		{
			$alert->warning('Je hebt geen email adres ingesteld voor je account. ');
		}
	}
}

if (!readconfigfromdb('mailenabled'))
{
	$alert->warning('E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
}
else if (!readconfigfromdb('support'))
{
	$alert->warning('Er is geen support mailadres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
}

$h1 = 'Help / Probleem melden';
$fa = 'ambulance';

require_once $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="subject" class="col-sm-2 control-label">Onderwerp</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="subject" name="subject" ';
echo 'value="' . $help['subject'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
echo '<div class="col-sm-10">';
echo '<textarea name="description" class="form-control" id="description" rows="4" required>';
echo $help['description'];
echo '</textarea>';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
generate_form_token();

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
