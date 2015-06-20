<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_passwords.php");
require_once($rootpath."includes/inc_form.php");
require_once($rootpath."includes/inc_mailfunctions.php");

//status 0: inactief
//status 1: letser
//status 2: uitstapper
//status 3: instapper
//status 4: secretariaat
//status 5: infopakket
//status 6: infoavond
//status 7: extern

$mode = $_GET["mode"];
$id = $_GET["id"];
$user = $contact = array();

if ($_POST['zend'])
{
	$user = array(
		'name'			=> $_POST['name'],
		'fullname'		=> $_POST['fullname'],
		'letscode'		=> $_POST['letscode'],
		'postcode'		=> $_POST['postcode'],
		'birthday'		=> $_POST['birthday'],
		'hobbies'		=> $_POST['hobbies'],
		'comments'		=> $_POST['comments'],
		'login'			=> $_POST['login'],
		'accountrole'	=> $_POST['accountrole'],
		'status'		=> $_POST['status'],
		'admincomment'	=> $_POST['admincomment'],
		'minlimit'		=> $_POST['minlimit'],
		'maxlimit'		=> $_POST['maxlimit'],
		'presharedkey'	=> $_POST['presharedkey'],
		'cron_saldo'	=> ($_POST['cron_saldo']) ? 't' : 'f',
		'lang'			=> 'nl',
	);

	$contact = $_POST['contact'];
	$activate = $_POST['activate'];

	foreach ($contact as $c)
	{
		if ($c['abbrev'] == 'mail' && $c['main_mail'])
		{
			$mail = $c['value'];
			break;
		}
	}

	if (!isset($mail))
	{
		$errors['mail'] = 'Geen mail adres ingevuld.';
	}
	else if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
	{
		$errors['mail'] = 'Geen geldig email adres.';
	}
	else if ($mode == 'new')
	{
		if ($db->GetOne('select c.value
			from contact c, type_contact tc
			where c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'
				and c.value = \'' . $mail . '\''))
		{
			$errors['mail'] = 'Het mailadres is al in gebruik.';
		}
		else
		{
			$errors = validate_input($user);
		}
	}
	else
	{
		if ($db->GetOne('SELECT c.value
			FROM contact c, type_contact tc
			WHERE c.id_user <> ' . $id . '
				AND c.id_type_contact = tc.id
				AND tc.abbrev = \'mail\'
				AND c.value = \'' . $mail . '\''))
		{
			$errors['mail'] = 'Het email adres is al in gebruik.';
		}
	}

	if (!count($errors))
	{
		$contact_types = $db->GetAssoc('SELECT abbrev, id FROM type_contact');

		if ($mode == 'new')
		{
			$user['creator'] = $s_id;
			$user['cdate'] = date('Y-m-d H:i:s');
			if ($user['activate'] || $user['status'] == 1)
			{
				$user["adate"] = date("Y-m-d H:i:s");
			}

			if ($activate)
			{
				$password = generatePassword(); 
				$user['password'] = hash('sha512', $password);
			}

			if ($db->AutoExecute('users', $user, 'INSERT'))
			{
				$alert->success('Gebruiker opgeslagen.');

				$id = $db->insert_ID();
				readuser($id, true);

				foreach ($contact as $value)
				{
					if (!$value['value'])
					{
						continue;
					}

					$insert = array(
						'value'				=> $value['value'],
						'flag_public'		=> ($value['flag_public']) ? 1 : 0,
						'id_type_contact'	=> $contact_types[$value['abbrev']],
						'id_user'			=> $id,
					);
					
					$db->AutoExecute('contact', $insert, 'INSERT');
				}

				// Activate the user if activate is set
				$mailenabled = readconfigfromdb('mailenabled');

				if($activate && !empty($mail) && $mailenabled)
				{
					$user['mail'] = $mail;
					sendactivationmail($password, $user);
					sendadminmail($user);
					$alert->success("OK - Activatiemail verstuurd");
				}
				else
				{
					$alert->warning('Geen activatiemail verstuurd.');
				}
				header('Location: view.php?id=' . $id);
				exit;

				if (!$mailenabled)
				{
					$alert->warning('Mailfuncties zijn uitgeschakeld.');
				}
			}
			else
			{
				$alert->error('Gebruiker niet opgeslagen.');
			}
		}
		else if ($id)
		{
			$user['mdate'] = date('Y-m-d H:i:s');
			if($db->AutoExecute('users', $user, 'UPDATE', 'id = ' . $id))
			{
				$alert->success('Gebruiker aangepast.');

				$stored_contacts = $db->GetAssoc('SELECT c.id, tc.abbrev, c.value, c.flag_public
					FROM type_contact tc, contact c
					WHERE tc.id = c.id_type_contact
						AND c.id_user = ' . $id);

				foreach ($contact as $value)
				{
					$stored_contact = $stored_contacts[$value['id']];

					$value['flag_public'] = ($value['flag_public']) ? 1 : 0;

					if (!$value['value'])
					{
						if ($stored_contact && !$value['main_mail'])
						{
							$db->Execute('DELETE FROM contact
								WHERE id_user = ' . $id . '
									AND id = ' . $value['id']);
						}
						continue;
					}

					if ($stored_contact['abbrev'] == $value['abbrev']
						&& $stored_contact['value'] == $value['value']
						&& $stored_contact['flag_public'] == $value['flag_public'])
					{
						continue;
					}

					if (!isset($stored_contact))
					{
						$insert = array(
							'id_type_contact'	=> $contact_types[$value['abbrev']],						
							'value'				=> $value['value'],
							'flag_public'		=> ($value['flag_public']) ? 1 : 0,
							'id_user'			=> $id,
						);
						$db->AutoExecute('contact', $insert, 'INSERT');
						continue;
					}

					$contact_update = $value;
					unset($contact_update['id'], $contact_update['abbrev'],
						$contact_update['name'], $contact_update['main_mail']);

					$db->AutoExecute('contact', $contact_update, 'UPDATE',
						'id = ' . $value['id'] . ' AND id_user = ' . $id);
				}

				header('Location: view.php?id=' . $id);
				exit;
			}
			else
			{
				$alert->error('Gebruiker niet aangepast.');
			}
		}
		else
		{
			$alert->error('Update niet mogelijk zonder id.');
		}
	}
	else
	{
		$alert->error('Fout in formulier: ' . implode(' | ', $errors));
	}
}
else
{
	$contact = $db->GetArray('select name, abbrev, \'\' as value, 0 as flag_public, 0 as id
		from type_contact
		where abbrev in (\'mail\', \'adr\', \'tel\', \'gsm\')');

	if ($mode == 'edit')
	{
		$contact_keys = array();

		foreach ($contact as $key => $c)
		{
			$contact_keys[$c['abbrev']] = $key;
		}

		$user = $db->GetRow('SELECT * FROM users WHERE id = ' . $id);

		$rs = $db->Execute('SELECT tc.abbrev, c.value, tc.name, c.flag_public, c.id
			FROM type_contact tc, contact c
			WHERE tc.id = c.id_type_contact
				AND c.id_user = ' . $id);

		while ($row = $rs->FetchRow())
		{
			if (isset($contact_keys[$row['abbrev']]))
			{
				$contact[$contact_keys[$row['abbrev']]] = $row;				
				unset($contact_keys[$row['abbrev']]);
				continue;
			}

			$contact[] = $row;
		}
	}
	else
	{
		$user = array(
			'minlimit'		=> readconfigfromdb('minlimit'),
			'maxlimit'		=> readconfigfromdb('maxlimit'),
			'accountrole'	=> 'user',
			'status'		=> '1',
			'cron_saldo'	=> 't',
		);
	}
}

array_walk($user, function(&$value, $key){ $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); });
array_walk($contact, function(&$value, $key){ $value['value'] = htmlspecialchars($value['value'], ENT_QUOTES, 'UTF-8'); });

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

include($rootpath."includes/inc_header.php");
echo '<h1>Gebruiker ' . (($mode == 'new') ? 'toevoegen' : 'wijzigen') . '</h1>';
echo "<div class='border_b'><p>";

echo '<form method="post">';
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td align='right' >";
echo "Naam";
echo "</td><td >";
echo '<input type="text" name="name" value="' . $user['name'] . '" size="30" required>';
echo "</td></tr>";

echo "<tr><td align='right'>";
echo "Volledige Naam (Voornaam en Achternaam)";
echo "</td><td >";
echo '<input type="text" name="fullname" size="30" value="' . $user['fullname'] . '" required>';
echo "</td></tr><tr><td ></td></tr>";

echo "<tr><td align='right'>Letscode</td>";
echo '<td ><input type="text" name="letscode" value="' . $user['letscode'] . '" size="30" maxlength="20" required>';
echo "</td></tr>";

echo "<tr><td align='right'>Postcode</td>";
echo '<td ><input type="text" name="postcode" value="' . $user['postcode'] . '" size="30" maxlength="6">';
echo "</td></tr>";

echo "<tr><td align='right'>Geboortedatum (jjjj-mm-dd)</td>";
echo "<td ><div></div><input type='text' name='birthday' value='" . $user['birthday'] . "' ";
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-default-view="2" ';
echo 'data-date-end-date="' . date('Y-m-d') . '" ';
echo 'data-date-language="nl" ';
echo 'data-date-start-view="2" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo 'data-date-immediate-updates="true" ';
echo "size='10'></div></td></tr>";

echo "<tr><td  align='right'>Hobbies/interesses:</td><td >";
echo "<textarea name='hobbies' cols='60' rows='4'>";
echo $user['hobbies'];
echo "</textarea>";
echo "</td></tr><tr><td></td><td >";
echo "</td></tr>";
echo "<tr><td align='right'>Commentaar</td><td >";
echo "<input type='text' name='comments' value='" . $user['comments'] . "' size='30'>";
echo "</td></tr>";

echo "<tr><td align='right'>Login</td><td >";
echo '<input type="text" name="login" value="' . $user['login'] . '" size="30">';
echo "</td></tr>";

echo "<tr><td align='right'>Rechten</td>";
echo "<td >";
$role_ary = array(
	'admin'		=> 'Admin',
	'user'		=> 'User',
	'guest'		=> 'Guest',
	'interlets'	=> 'Interlets',
);
echo "<select name='accountrole' id='accountrole'>";
render_select_options($role_ary, $user['accountrole']);
echo "</select>";
echo "</td></tr>";
echo "<tr><td  align='right'>Status</td>";
echo "<td >";
echo '<select name="status">';
$status_ary = array(
	0	=> 'Gedesactiveerd',
	1	=> 'Actief',
	2	=> 'Uitstapper',	
	5	=> 'Infopakket',
	6	=> 'Infoavond',
	7	=> 'Extern',
);
render_select_options($status_ary, $user['status']);
echo "</select>";
echo "</td>";
echo "</tr>";

echo "<tr><td align='right'>Commentaar van de admin:</td><td >";
echo "<textarea name='admincomment' cols='60' rows='4'>";
echo $user['admincomment'];
echo "</textarea>";
echo "</td></tr><tr><td >";
echo "</td></tr>";

echo "<tr><td align='right'>Limiet minstand</td><td >";
echo "<input type='number' name='minlimit' value='" . $user['minlimit'] . "' size='30' required>";
echo "</td></tr>";

echo "<tr><td align='right'>Limiet maxstand</td><td >";
echo "<input type='number' name='maxlimit' value='" . $user['maxlimit'] . "' size='30' required>";
echo "</td></tr>";

echo "<tr><td  align='right'>Preshared key<br><small><i>Interlets veld</i></small></td><td >";
echo "<input type='text' name='presharedkey' value='" . $user['presharedkey'] . "' size='30'>";
echo "</td></tr><tr><td></td>";
echo "</tr>";

echo "<tr><td  align='right'>Saldo mail met recent vraag en aanbod</td><td >";
echo "<input type='checkbox' name='cron_saldo'";
echo ($user['cron_saldo'] == 't') ? ' checked="checked"' : '';
echo ">";
echo "</td></tr><tr><td></td>";
echo "</tr>";

echo '<tr class="contact"><td colspan="2"><h2>Contacten</h2></td></tr>';

$already_one_mail_input = false;

foreach ($contact as $key => $c)
{
	echo '<tr class="contact"><td  align="right">' . $c['name'] . '</td><td >';
	echo '<input name="contact[' . $key . '][value]" value="' . $c['value'] . '" size="30"';
	echo ($c['abbrev'] == 'mail' && !$already_one_mail_input) ? ' required="required"' : '';
	echo ($c['abbrev'] == 'mail') ? ' type="email"' : ' type="text"';
	echo '>';
	echo '</td></tr>';

	echo '<tr class="contact"><td  align="right"></td><td>';
	echo '<input type="checkbox" name="contact[' . $key . '][flag_public]" value="1"';
	echo  ($c['flag_public']) ? ' checked="checked"' : '';
	echo '>zichtbaar</td></tr>';

	if ($c['abbrev'] == 'mail' && !$already_one_mail_input)
	{
		echo '<input type="hidden" name="contact['. $key . '][main_mail]" value="1">';
	}
	echo '<input type="hidden" name="contact['. $key . '][id]" value="' . $c['id'] . '">';
	echo '<input type="hidden" name="contact['. $key . '][name]" value="' . $c['name'] . '">';
	echo '<input type="hidden" name="contact['. $key . '][abbrev]" value="' . $c['abbrev'] . '">';

	$already_one_mail_input = ($c['abbrev'] == 'mail') ? true : $already_one_mail_input;
}

if ($mode == 'new')
{
	echo "<tr><td  align='right'>Activeren?</td><td >";
	echo "<input type='checkbox' name='activate' ";
	echo "checked='checked'";
	echo ">";
	echo "</td></tr>";
}

echo "<tr><td></td><td>";
echo "<input type='submit' name='zend' value='Opslaan'>";
echo "</td></tr></table>";
echo "</form>";

include($rootpath."includes/inc_footer.php");

function validate_input($posted_list)
{
	global $db;	
	$error_list = array();
	if (!isset($posted_list["name"])|| $posted_list["name"]=="")
	{
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>naam</strong> in!</font>";
	}

	if ($db->GetOne('select letscode from users where letscode = \'' . $posted_list['letscode'] . '\''))
	{
		$error_list['letscode']= 'Letscode bestaat al!';
	}

	if (!empty($posted_list['login'])
		&& $db->GetOne('select login from users where login = \'' . $posted_list['login'] . '\''))
	{
		$error_list['login'] = 'Login bestaat al!';
	}

	if (!filter_var($posted_list['minlimit'] ,FILTER_VALIDATE_INT))
	{
		$error_list['minlimit'] = 'Geef getal op voor de minimum limiet.';
	}

	if (!filter_var($posted_list['maxlimit'] ,FILTER_VALIDATE_INT))
	{
		$error_list['maxlimit'] = 'Geef getal op voor de maximum limiet.';
	}

	return $error_list;
}

function sendadminmail($user)
{
	$mailfrom = trim(readconfigfromdb("from_address"));
	$mailto = trim(readconfigfromdb("admin"));
	$systemtag = readconfigfromdb("systemtag");

	$mailsubject = "[eLAS-";
	$mailsubject .= readconfigfromdb("systemtag");
	$mailsubject .= "] eLAS account activatie";

	$mailcontent  = "*** Dit is een automatische mail van het eLAS systeem van ";
	$mailcontent .= $systemtag;
	$mailcontent .= " ***\r\n\n";
	$mailcontent .= "De account ";
	$mailcontent .= $user["login"];
	$mailcontent .= ' ( ' . $user['letscode'] . ' ) ';
	$mailcontent .= " werd geactiveerd met een nieuw passwoord.\n";
	if (!empty($user["mail"])){
			$mailcontent .= "Er werd een mail verstuurd naar de gebruiker op ";
			$mailcontent .= $user["mail"];
			$mailcontent .= ".\n\n";
	} else {
			$mailcontent .= "Er werd GEEN mail verstuurd omdat er geen E-mail adres bekend is voor de gebruiker.\n\n";
	}

	$mailcontent .= "OPMERKING: Vergeet niet om de gebruiker eventueel toe te voegen aan andere LETS programma's zoals mailing lists.\n\n";
	$mailcontent .= "Met vriendelijke groeten\n\nDe eLAS account robot\n";

	sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
}
