<?php

$rootpath = './';
$role = 'anonymous';
$allow_anonymous_post = true;
$allow_session = true;

require_once $rootpath . 'includes/inc_default.php';

if(isset($_POST['zend']))
{
	$help = array();
	$help['letscode'] = $_POST['letscode'];
	$help['mail'] = $_POST['mail'];
	$help['subject'] = $_POST['subject'];
	$help['description'] = $_POST['description'];
	$help['browser'] = $_SERVER['HTTP_USER_AGENT'];

    $errors = array();

	if (!$s_id)
	{
		if(empty($help['letscode']))
		{
			$errors[] = 'Vul je letscode in';
		}

		if(empty($help['mail']))
		{
			$errors[] = 'Vul een E-mail adres in';
		}

		if(!($db->fetchColumn('select c.value
			from contact c, type_contact tc
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.value = ?', array($help['mail']))))
		{
			$errors[] = 'Dit mailadres is niet gekend in deze installatie';
		}

		if (!($help['user_id'] = $db->fetchColumn('select c.id_user
			from contact c, type_contact tc, users u
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.value = ?
				and c.id_user = u.id
				and u.letscode = ?', array($help['mail'], $help['letscode']))))
		{
			$errors[] = 'Gebruiker niet gevonden.';
		}
	}
	else
	{
		$help['user_id'] = $s_id;
	}

	if(empty($help['subject']))
	{
        $errors[] = 'Geef een onderwerp op.';
	}

	if(empty($help['description']))
	{
		$errors[] = 'Geef een omschrijving van je probleem.';
	}

	if(empty($errors))
	{
		if (!($return_message = helpmail($help)))
		{
			$alert->success('De support mail is verzonden.');
			header('Location: ' . generate_url(($s_anonymous) ? 'login' : 'index'));
			exit;
		}

		$alert->error('Mail niet verstuurd. ' . $return_message);
	}
	else
	{
		$alert->error(implode('<br>', $errors));
	}
}
else
{
	if(isset($s_id))
	{
		$user = readuser($s_id);

		$help['mail'] = $db->fetchColumn('select c.value
			from contact c, type_contact tc
			where c.id_type_contact = tc.id
				and c.id_user = ?
				and tc.abbrev = \'mail\'', array($s_id));

		$help['letscode'] = $user['letscode'];
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
 
$readonly = ($s_id) ? ' readonly' : '';

$h1 = 'Help / Probleem melden';
$fa = 'ambulance';

require_once $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

if ($s_id)
{
	echo '<div style="display:none;">';
}

echo '<div class="form-group">';
echo '<label for="letscode" class="col-sm-2 control-label">Letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="letscode" name="letscode" ';
echo 'value="' . $help['letscode'] . '" required' . $readonly . '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="mail" class="col-sm-2 control-label">Email (waarmee je in deze installatie geregistreerd bent)</label>';
echo '<div class="col-sm-10">';
echo '<input type="email" class="form-control" id="mail" name="mail" ';
echo 'value="' . $help['mail'] . '" required' . $readonly . '>';
echo '</div>';
echo '</div>';

if ($s_id)
{
	echo '</div>';
}

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

echo '</form>';

echo '</div>';
echo '</div>';

if (!$s_id)
{
	echo '<small><i>Opgelet: je kan vanuit het loginscherm zelf een nieuw paswoord aanvragen met je e-mail adres!</i></small>';
}

include $rootpath . 'includes/inc_footer.php';

function helpmail($help)
{
	global $rootpath, $s_id, $db, $systemtag;

	$from = $help['mail'];

	$to = trim(readconfigfromdb('support'));

	if (empty($to))
	{
		return 'Het support email adres is niet ingesteld op deze installatie';
	}

	$subject = '[' . $systemtag . '] ' .$help['subject'];

    $content  = "-- via de website werd het volgende probleem gemeld --\r\n";
	$content .= 'E-mail: ' . $help['mail'] . "\r\n";

	$content .= 'Gebruiker: ' . link_user($help['user_id'], null, false, true) . "\r\n";

	$content .= 'Gebruiker ingelogd: ';
	$content .= ($s_id) ? 'Ja' : 'Nee (Opmerking: het is niet geheel zeker dat dit is de gebruiker zelf is. ';
	$content .= ($s_id) ? '' : 'Iemand anders die het email adres en de letscode kent, kan dit bericht verzonden hebben).';
	$content .= "\r\n\r\n";
	$content .= "Omschrijving:\r\n";
	$content .= $help['description'] . "\r\n";
	$content .= "\r\n";
	$content .= "User Agent:\r\n";
	$content .= $help['browser'] . "\r\n";
	$content .= "\r\n";
	$content .= "eLAS versie: Heroku \r\n";
	$content .= 'Webserver: ' . gethostname() . "\r\n";

	return sendemail($from, $to, $subject, $content);
}
