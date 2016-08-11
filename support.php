<?php

$rootpath = './';
$page_access = 'user';

require_once $rootpath . 'includes/inc_default.php';

if (isset($_POST['zend']))
{
	$description = $_POST['description'] ?? '';
	$desctiption = trim($description);

	if(empty($description) || strip_tags($description) == '' || $description === false)
	{
		$errors[] = 'Het bericht is leeg.';
	}

	if (!trim(readconfigfromdb('support')))
	{
		$errors[] = 'Het support email adres is niet ingesteld op deze installatie';
	}

	if ($s_master)
	{
		$errors[] = 'Het master account kan geen berichten versturen.';
	}

	if ($token_error = $app['eland.form_token']->get_error())
	{
		$errors[] = $token_error;
	}

	$my_contacts = $app['db']->fetchAll('select c.value, tc.abbrev
		from contact c, type_contact tc
		where c.id_user = ?
			and c.id_type_contact = tc.id', [$s_id]);

	$mailaddr = getmailadr($s_id);

	$mail_ary = [
		'to'		=> 'support',
		'subject' 	=> 'Support voor ' . link_user($s_id, false, false)
	];

	foreach ($my_contacts as $my_contact)
	{
		if ($my_contact['abbrev'] == 'mail')
		{
			$mail_ary['reply_to'] = $s_id;
		}
	}

	if(!count($errors))
	{
		$html = '<p>Gebruiker ' . link_user($s_id, false, false) . ' deed melding via het ';
		$html .= 'supportformulier op de website.';
		$html .= '</p>';

		$html .= '<hr>';
		$html .= $description;
		$html .= '<hr>';
		$html .= '<p>';

		if ($mail_ary['reply_to'])
		{
			$html .= 'Om te antwoorden kan je gewoon reply kiezen of de contactgegevens hieronder gebruiken';
		}
		else
		{
			$html .= 'Er is geen mailadres gekend van ' . link_user($s_id, false, false);
			$html .= '. Contacteer de gebruiker op andere wijze.';
		}

		$html .= '</p>';

		$html .= '<h4>Contactgegevens van ' . link_user($s_id, false, false) . '</h4><ul>';

		foreach($my_contacts as $value)
		{
			$html .= '<li>' . $value['abbrev'] . "\t" . $value['value'] . '</li>';
		}
		
		$html .= '</ul>';

		$config_htmlpurifier = HTMLPurifier_Config::createDefault();
		$config_htmlpurifier->set('Cache.DefinitionImpl', null);
		$htmlpurifier = new HTMLPurifier($config_htmlpurifier);
		$html = $htmlpurifier->purify($html);

		$mail_ary['html'] = $html;

		$return_message =  mail_q($mail_ary);

		if (!$return_message)
		{
			$app['eland.alert']->success('De support mail is verzonden.');
			header('Location: ' . generate_url('index'));
			exit;
		}

		$app['eland.alert']->error('Mail niet verstuurd. ' . $return_message);
	}
	else
	{
		$app['eland.alert']->error($errors);
	}
}
else
{
	$description = '';

	if ($s_master)
	{
		$app['eland.alert']->warning('Het master account kan geen berichten versturen.');
	}
	else
	{
		$mail = getmailadr($s_id);

		if (!count($mail))
		{
			$app['eland.alert']->warning('Je hebt geen email adres ingesteld voor je account. ');
		}
	}
}

if (!readconfigfromdb('mailenabled'))
{
	$app['eland.alert']->warning('E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
}
else if (!readconfigfromdb('support'))
{
	$app['eland.alert']->warning('Er is geen support mailadres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
}

$h1 = 'Help / Probleem melden';
$fa = 'ambulance';

$app['eland.assets']->add(['summernote', 'rich_edit.js']);

require_once $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

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
//echo '<label for="description" class="col-sm-2 control-label">Je bericht</label>';
echo '<div class="col-sm-12">';
echo '<textarea name="description" class="form-control rich-edit" id="description" rows="4">';
echo $description;
echo '</textarea>';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
$app['eland.form_token']->generate();

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
