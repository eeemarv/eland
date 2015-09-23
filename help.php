<?php
ob_start();
$rootpath = './';
$role = 'anonymous';
require_once $rootpath . 'includes/inc_default.php';

if(isset($_POST['zend']))
{
	$help = array(); 
	$help['login'] = $_POST["login"];
	$help['email'] = $_POST["email"];
	$help['subject'] = $_POST["subject"];
	$help['description'] = $_POST["description"];
	$help['browser'] = $_SERVER['HTTP_USER_AGENT'];

    $errors = array();
   
	if(empty($help['login']))
	{
		$errors[] = 'Vul een eLAS login in';
	}

	if(empty($help['email']))
	{
		$errors[] = 'Vul een E-mail adres in';
	}

	if(!($db->fetchColumn('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'
			and c.value = ?', array($help['email']))))
	{
		$errors[] = 'Dit mailadres is niet gekend in eLAS';
	}

	if(empty($help['subject']))
	{
        $errors[] = 'Geef een onderwerp op.';
	}

	if(empty($help['description']))
	{
		$errors[] = 'Geef een omschrijving van je probleem<.';
	}

	if(empty($errors))
	{
		if (!($return_message = helpmail($help)))
		{
			$alert->success('De support mail is verzonden.');
			header('Location: index.php');
			exit;
		}

		$alert->error('Mail niet verstuurd. ' . $return_message);
	}
	else
	{
		$alert->error('Fouten in het mail formulier.');
	}
}
else
{
	if(isset($s_id))
	{
		$user = readuser($s_id);
		$help['login'] = $user['login'];
		$help['email'] = $db->fetchColumn('select c.value
			from contact c, type_contact tc
			where c.id_type_contact = tc.id
				and c.id_user = ?
				and tc.abbrev = \'mail\'', array($s_id));
	}
}

if (!readconfigfromdb("mailenabled"))
{
	$alert->warning("E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken");
}
else if (!readconfigfromdb('support'))
{
	$alert->warning('Er is geen support mailadres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
}
 
$readonly = ($s_id) ? ' readonly' : '';

$h1 = 'eLAS help / Probleem melden';
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
echo '<label for="login" class="col-sm-2 control-label">Login</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="login" name="login" ';
echo 'value="' . $help['login'] . '" required' . $readonly . '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="login" class="col-sm-2 control-label">Email (waarmee je in eLAS geregistreerd bent)</label>';
echo '<div class="col-sm-10">';
echo '<input type="email" class="form-control" id="email" name="email" ';
echo 'value="' . $help['email'] . '" required' . $readonly . '>';
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



function helpmail($help,$rootpath)
{

	global $rootpath, $s_id;

	$from = trim($help['email']);

	$to = trim(readconfigfromdb('support'));
	if (empty($to))
	{
		return false;
	}

	$subject = '[eLAS-' . readconfigfromdb('systemtag') . '] ' .$help['subject'];

    $content  = "-- via de eLAS website werd het volgende probleem gemeld --\r\n";
	$content .= "E-mail: {$help['email']}\r\n";
	$content .= "Login:  {$help['login']}\r\n";
	if ($s_id)
	{
		$user = readuser($s_id);
		$content .= "Letscode:  {$user['letscode']}\r\n";
	}
	$content .= "Omschrijving:\r\n";
	$content .= "{$help['description']}\r\n";
	$content .= "\r\n";
	$content .= "User Agent:\r\n";
	$content .= "{$help['browser']}\r\n";
	$content .= "\r\n";
	$content .= "eLAS versie: Heroku \r\n";
	$content .= "Webserver: " .gethostname() ."\r\n";

	return sendemail($from, $to, $subject, $content);

}
