<?php
ob_start();
//$ptitle="login";
$rootpath = "./";
$role = 'anonymous';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_mailfunctions.php");

if(isset($_POST["zend"])){
	$posted_list = array(); 
	$posted_list["login"] = mysql_escape_string($_POST["login"]);
	$posted_list["email"] = mysql_escape_string($_POST["email"]);
	$posted_list["subject"] = mysql_escape_string($_POST["subject"]);
	$posted_list["description"] = mysql_escape_string($_POST["description"]);
	$posted_list["browser"] = $_SERVER['HTTP_USER_AGENT'];
	$error_list = validate_input($posted_list);

	if(empty($error_list)){
		if (!($return_message = helpmail($posted_list)))
		{
			$alert->success("Support mail verstuurd");
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
	if(isset($s_id)){
		$user = get_user_maildetails($s_id);
		$posted_list['login'] = $user['login'];
		$posted_list['email'] = $user['emailaddress'];
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

require_once($rootpath."includes/inc_header.php");

echo "<table border='0' width='100%'><tr><td><h1>eLAS Help</h1>";
echo "</td><td align='right'>";
echo "</td></tr></table>";

echo "<form action='help.php' method='post'>";
echo "<table cellpadding='0' cellspacing='0' border='0'>";
echo "<tr><td>";
echo "Loginnaam";
echo "</td><td>";

echo "<input type='text' name='login' size='30' value='" .$posted_list["login"] ."'>";

echo "</td><td>";
if(!empty($error_list["login"])){
	echo $error_list["login"];
}
echo "</td></tr>";
echo "<tr><td>";
echo "E-mail adres<br><small><i>(Dit adres moet in eLAS geregistreerd staan)</i></small>";
echo "</td><td>";

echo "<input type='email' name='email' size='30' value='" .$posted_list["email"] ."' required>";

echo "</td><td>";
if(!empty($error_list["email"])){
	echo $error_list["email"];
}
echo "</td></tr>";
// Subject line
echo "<tr><td>";
echo "Onderwerp<br><small><i>(verplicht, bv. Inloggen lukt niet)</i></small><br>";
echo "</td><td>";

echo "<input type='text' name='subject' size='30' value='" .$posted_list["subject"] ."' required>";

echo "</td><td>";
if(!empty($error_list["subject"])){
	echo $error_list["subject"];
}
echo "</td></tr>";
	// subject
echo "<tr><td>";
echo "Omschrijving van je probleem:<br>";
echo "</td><td>";

echo "<TEXTAREA NAME='description' COLS=60 ROWS=6 required>" .$posted_list["description"] ."</TEXTAREA>";

echo "</td></tr>";
echo "<tr><td>";
if(!empty($error_list["description"])){
	echo $error_list["description"];
}
echo "</td></tr>";
echo "<tr><td></td><td>";

echo "<input type='submit' name='zend' value='Verzenden'>";
echo "</td><td>&nbsp;</td></tr></table>";
echo "</form>";

echo "<small><i>Opgelet: je kan vanuit het loginscherm zelf een nieuw password aanvragen met je e-mail adres!</i></small>";

include $rootpath . 'includes/inc_footer.php';

function validate_input($posted_list){
        $error_list = array();
	if(empty($posted_list["login"])){
	                $error_list["login"] = "<font color='red'> Vul een <strong>eLAS login</strong> in</font>";
	}
	if(empty($posted_list["email"])){
		$error_list["email"] = "<font color='red'> Vul een <strong>E-mail adres</strong> in</font>";
	}
	$checkedaddress = checkmailaddress($posted_list["email"]);
	if(empty($checkedaddress)) {
			$error_list["email"] = "<font color='red'> Dit mailadres is niet gekend in eLAS</font>";
	}
	if(empty($posted_list["subject"])){
                $error_list["subject"] = "<font color='red'> Geef een <strong>onderwerp</stong> op</font>";
	}
	if(empty($posted_list["description"])){
		$error_list["description"] = "<font color='red'> Geef een <strong>omschrijving</strong> van je probleem</font>";
	}
        return $error_list;
}

function checkmailaddress($email){
	global $db;
	$query = "SELECT contact.value FROM contact, type_contact WHERE id_type_contact = type_contact.id and type_contact.abbrev = 'mail' AND contact.value = '" .$email ."'";
	$checkedaddress = $db->GetRow($query);
	return $checkedaddress;
}

function get_user_maildetails($userid){
        global $db;
        $user = readuser($userid);
        $query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
        $contacts = $db->GetRow($query);
        $user["emailaddress"] = $contacts["value"];

        return $user;
}

function helpmail($posted_list,$rootpath){

	global $rootpath, $s_id;

	$mailfrom = trim($posted_list['email']);
	

	$mailto = trim(readconfigfromdb("support"));
	if (empty($mailto)){
		return false;
	}

	$mailsubject = readconfigfromdb("systemtag") ." - " .$posted_list['subject'];

    $mailcontent  = "-- via de eLAS website werd het volgende probleem gemeld --\r\n";
	$mailcontent .= "E-mail: {$posted_list['email']}\r\n";
	$mailcontent .= "Login:  {$posted_list['login']}\r\n";
	if ($s_id){
		$user = readuser($s_id);
		$mailcontent .= "Letscode:  {$user['letscode']}\r\n";
	}
	$mailcontent .= "Omschrijving:\r\n";
	$mailcontent .= "{$posted_list['description']}\r\n";
	$mailcontent .= "\r\n";
	$mailcontent .= "User Agent:\r\n";
        $mailcontent .= "{$posted_list['browser']}\r\n";
	$mailcontent .= "\r\n";
	$mailcontent .= "eLAS versie: Heroku \r\n";// .$elas->version ."-" .$elas->branch ."-r" .$elas->revision ."\r\n";
	$mailcontent .= "Webserver: " .gethostname() ."\r\n";

	return sendemail($mailfrom, $mailto, $mailsubject, $mailcontent);

}
