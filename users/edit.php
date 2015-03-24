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
		'name'			=> pg_escape_string($_POST['name']),
		'fullname'		=> pg_escape_string($_POST['fullname']),
		'letscode'		=> pg_escape_string($_POST['letscode']),
		'postcode'		=> pg_escape_string($_POST['postcode']),
		'birthday'		=> $_POST['birthday'],
		'hobbies'		=> pg_escape_string($_POST['hobbies']),
		'comments'		=> pg_escape_string($_POST['comments']),
		'login'			=> pg_escape_string($_POST['login']),
		'accountrole'	=> pg_escape_string($_POST['accountrole']),
		'admincomment'	=> pg_escape_string($_POST['admincomment']),
		'minlimit'		=> $_POST['minlimit'],
		'maxlimit'		=> $_POST['maxlimit'],
		'presharedkey'	=> pg_escape_string($_POST['presharedkey']),
		'lang'			=> 'nl',
	);
	$contact = array(
		'mail'			=> pg_escape_string($_POST['mail']),
		'tel'			=> pg_escape_string($_POST['tel']),
		'gsm'			=> pg_escape_string($_POST['gsm']),
		'addr'			=> pg_escape_string($_POST['addr']),
	);
	$activate = $_POST['activate'];

	if ($mode == 'new')
	{
		$errors = validate_input($user);
	}
	else
	{
		if ($db->GetOne('SELECT c.value
			FROM contact c, type_contact tc
			WHERE c.id_user <> ' . $id . '
				AND c.id_type_contact = tc.id
				AND tc.abbrev = \'mail\'
				AND c.value = \'' . $contact['mail']))
		{
			$error['mail'] = 'Het email adres is al in gebruik.';
		}
	}

	if (!$contact['mail'] || !filter_var($contact['mail'], FILTER_VALIDATE_EMAIL))
	{
		$errors['mail'] = 'Geen geldig email adres.';
	}

	if (!count($errors))
	{
		if ($mode == 'new')
		{
			$user['creator'] = $s_id;
			$user['cdate'] = date('Y-m-d H:i:s');

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

				$contact_types = $db->GetAssoc('SELECT abbrev, id FROM type_contact');

				foreach ($contact as $key => $value)
				{
					if (!$value)
					{
						continue;
					}
					
					$insert = array(
						'value'		=> $value,
						'id_type_contact'	=> $contact_types[$key],
						'id_user'			=> $id,
					);
					$db->AutoExecute('contact', $insert, 'INSERT');
				}

				// Activate the user if activate is set
				if($activate && !empty($contact['mail']))
				{
					$user['mail'] = $contact['mail'];
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

				$contact_types = $db->GetAssoc('SELECT abbrev, id FROM type_contact');
				$stored_contacts = $db->GetAssoc('SELECT tc.abbrev, c.value
					FROM type_contact tc, contact c
					WHERE tc.id = c.id_type_contact
						AND c.id_user = ' . $id);

				foreach ($contact as $key => $value)
				{
					if (!$value)
					{
						if ($stored_contacts[$key] && $key != 'mail')
						{
							$db->Execute('DELETE FROM contact
								WHERE id_user = ' . $id . '
									AND id_type_contact = ' . $contact_types[$key]);
						}
						continue;
					}

					if ($stored_contacts[$key] == $value)
					{
						continue;
					}

					if (!$stored_contacts[$key])
					{
						$insert = array(
							'value'		=> $value,
							'id_type_contact'	=> $contact_types[$key],
							'id_user'			=> $id,
						);
						$db->AutoExecute('contact', $data, 'INSERT');
						continue;
					}

					$db->AutoExecute('contact', array('value' => $value), 'UPDATE',
						'id_user = ' . $id . ' AND id_type_contact = \'' . $contact_types[$key] . '\'');
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
	if ($mode == 'edit')
	{
		$user = $db->GetRow('SELECT * FROM users WHERE id = ' . $id);
		$contact = $db->GetAssoc('SELECT tc.abbrev, c.value
			FROM type_contact tc, contact c
			WHERE tc.id = c.id_type_contact
				AND c.id_user = ' . $id);
	}
	else
	{
		$user = array(
			'minlimit'		=> readconfigfromdb('minlimit'),
			'maxlimit'		=> readconfigfromdb('maxlimit'),
			'accountrole'	=> 'user',
			'status'		=> '1',
		);
	}
}

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>
	<script src="' . $rootpath . 'js/users_edit.js"></script>';

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
echo "<input type='text' name='fullname' size='30' value='" . $user['fullname'] . "' required>";
echo "</td></tr><tr><td ></td></tr>";

echo "<tr><td align='right'>Letscode</td>";
echo "<td ><input type='text' name='letscode' value='" . $user['letscode'] . "' size='30' required>";
echo "</td></tr>";

echo "<tr><td align='right'>Postcode</td>";
echo "<td ><input type='text' name='postcode' value='" . $user['postcode'] . "' size='30'>";
echo "</td></tr>";

echo "<tr><td align='right'>Geboortedatum (jjjj-mm-dd)</td>";
echo "<td ><div></div><input type='text' name='birthday' value='" . $user['birthday'] . "' ";
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-default-view="2" ';
echo 'data-date-end-date="' . date('Y-m-d') . '" ';
echo 'data-date-language="nl" ';
echo 'data-date-start-view="2" ';
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
echo "<input type='text' name='login' value='" . $user['login'] . "' size='30'>";
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

echo "<tr><td  align='right'>E-mail</td><td >";
echo "<input type='email' name='mail' value='" . $contact['mail'] . "' size='30' required>";
echo "</td></tr>";

echo "<tr><td  align='right'>Adres</td><td >";
echo "<input type='text' name='addr' value='" . $contact['addr'] . "' size='30'>";
echo "</td></tr>";

echo "<tr><td  align='right'>Tel</td><td >";
echo "<input type='text' name='tel' value='" . $contact['tel'] . "' size='30'>";
echo "</td></tr>";

echo "<tr><td  align='right'>GSM</td><td >";
echo "<input type='text' name='gsm' value='" . $contact['gsm'] . "' size='30'>";
echo "</td></tr>";

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


function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["name"])|| $posted_list["name"]==""){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>naam</strong> in!</font>";
	}
	global $db;
	$query = "SELECT * FROM users ";
	$query .= "WHERE TRIM(letscode)  <> '' ";
	$query .= "AND TRIM(letscode) = '".$posted_list["letscode"]."'";
	$query .= " AND status <> 0 ";
	$rs=$db->Execute($query);
	$number2 = $rs->recordcount();

	if ($number2 !== 0){
		$error_list["letscode"]="Letscode bestaat al!";
	}

	if (!empty($posted_list["login"])){
	    $query = "SELECT * FROM users WHERE login = '".$posted_list["login"]."'";
    	    $rs=$db->Execute($query);
	    $number = $rs->recordcount();

	    if ($number !== 0){
		$error_list["login"]="Login bestaat al!";
	    }
	}

	//amount may not be empty
	$var = trim($posted_list["minlimit"]);
	if (empty($posted_list["minlimit"])|| (trim($posted_list["minlimit"] )=="")){
		$error_list["minlimit"]="Vul bedrag in!";
	//amount amy only contain  numbers between 0 en 9
	}elseif(eregi('^-[0-9]+$', $var) == FALSE){
		$error_list["minlimit"]="Bedrag moet een negatief getal,zijn!";
	}
	return $error_list;
}

function sendadminmail($user)
{
	$mailfrom = trim(readconfigfromdb("from_address"));
	$mailto = trim(readconfigfromdb("admin"));
	$systemtag = readconfigfromdb("systemtag");

	$mailsubject = "[";
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
