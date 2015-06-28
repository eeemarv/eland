<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';
require_once $rootpath . 'includes/inc_userinfo.php';
require_once $rootpath . 'includes/inc_mailfunctions.php';
require_once $rootpath . 'includes/inc_passwords.php';

if (!isset($_GET['id']))
{
	header('Location: overview.php');
	exit;
}

$id = $_GET['id'];

$user = get_user_maildetails($id);

$user = $db->GetRow('SELECT u.*, c.value as mail
	FROM users u, contact c, type_contact tc
	WHERE u.id = c.id_user
		AND c.id_type_contact = tc.id
		AND tc.abbrev = \'mail\'
		AND u.id = ' . $id);

if(isset($_POST["zend"])){
	$posted_list = array();
	$posted_list["pw1"] = $_POST["pw1"];
	$posted_list["pw2"] = $_POST["pw2"];
	$posted_list["adate"] = date("Y-m-d H:i:s");
	$errorlist = validate_input($posted_list,$configuration);
	if (empty($errorlist)){
		sendactivationmail($posted_list["pw1"], $user, $s_id);
		sendadminmail($posted_list, $user);
		update_password($id, $posted_list);
		set_adate($id);

		$userlogin = $user["login"];
		log_event($s_id,"Act","Account $userlogin activated");
		$alert->success("Account $userlogin geactiveerd", 0);
		header('Location: view.php?id=' . $id);
		exit;
	}

	$alert->error('Fout in formulier. Activatie emails niet verzonden.');
}

include $rootpath . 'includes/inc_header.php';
echo "<h1>Account activeren</h1>";
show_pwform($errorlist, $id, $user);
include($rootpath."includes/inc_footer.php");

////////////////

function sendadminmail($posted_list, $user){
        global $configuration;
        if (!empty(readconfigfromdb("admin"))){
                $mailfrom .= trim(readconfigfromdb("from_address"));
                $mailto .= trim(readconfigfromdb("admin"))."\r\n";
        }else {
                 $alert->error("No admin adress set in config, not sending");
                 return 0;
        }

        $mailsubject = "[eLAS-";
        $mailsubject .= readconfigfromdb("systemtag");
        $mailsubject .= "] eLAS account activatie";

        $mailcontent  = "*** Dit is een automatische mail van het eLAS systeem van ";
        $mailcontent .= readconfigfromdb("systemtag");
        $mailcontent .= " ***\r\n\n";
	$mailcontent .= "De account ";
	$mailcontent .= $user["login"];
	$mailcontent .= " werd geactiveerd met een nieuw passwoord.\n";
	if (!empty($user["emailaddress"]))
	{
		$mailcontent .= "Er werd een mail verstuurd naar de gebruiker op ";
		$mailcontent .= $user["emailaddress"];
		$mailcontent .= ".\n\n";
	} else {
		$mailcontent .= "Er werd GEEN mail verstuurd omdat er geen E-mail adres bekend is voor de gebruiker.\n\n";
	}

	$mailcontent .= "OPMERKING: Vergeet niet om de gebruiker eventueel toe te voegen aan andere LETS programma's zoals mailing lists.\n\n";
	$mailcontent .= "Met vriendelijke groeten\n\nDe eLAS account robot\n";

        echo "<br>Bezig met het verzenden naar de beheerder op $mailto...\n";
        sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
        echo "OK<br>";
}

function set_adate($id){
	$posted_list["adate"] = date("Y-m-d H:i:s");
	$result = update_user($id, $posted_list);
}

function update_user($id, $posted_list){
    global $db;
    $posted_list["mdate"] = date("Y-m-d H:i:s");
    $result = $db->AutoExecute("users", $posted_list, 'UPDATE', "id=$id");
    readuser($id, true);
    return $result;
}

function validate_input($posted_list,$configuration){
	$errorlist = array();
	if (empty($posted_list["pw1"]) || (trim($posted_list["pw1"]) == "")){
		$errorlist["pw1"] = "<font color='#F56DB5'>Vul <strong>paswoord</strong> in!</font>";
	}
        $pwscore = Password_Strength($posted_list["pw1"]);
        $pwreqscore = readconfigfromdb("pwscore");
        if ($pwscore < $pwreqscore){
                $errorlist["pw1"] = "<font color='#F56DB5'>Paswoord is te zwak (score $pwscore/$pwreqscore)</font>";
        }

	if (empty($posted_list["pw2"]) || (trim($posted_list["pw2"]) == "")){
		$errorlist["pw2"] = "<font color='#F56DB5'>Vul <strong>paswoord</strong> in!</font>";
	}
	if ($posted_list["pw1"] !== $posted_list["pw2"]){
	$errorlist["pw3"] = "<font color='#F56DB5'><strong>Paswoorden zijn niet identiek</strong>!</font>";
	}
	return $errorlist;
}

function show_pwform($errorlist, $id, $user)
{
	$pw = GeneratePassword();
	
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	
	echo "<div class='border_b'>";
	echo "<form action='activate.php?id=".$id."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>User</td>";
	echo "<td valign='top'>";
	echo $user["name"];
	echo "</td></tr>";
	echo "<tr><td valign='top' align='right'>E-mail</td>";
	echo "<td valign='top'>";
	echo $user["mail"];
	echo "</td></tr>";
	echo "<tr><td>Paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='text' name='pw1' size='30' value='";
	echo $pw;
	echo "' required>";
	echo "</td>";
	echo "<td>";
		if (isset($errorlist["pw1"])){
			echo $errorlist["pw1"];
		}
	echo "</td>";
	echo "</tr>";
	echo "<tr><td valign='top' align='right'>Herhaal paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='text' name='pw2' size='30' value='";
        echo $pw;
        echo "' required>";
	echo "</td>";
	echo "<td>";
		if (isset($errorlist["pw2"])){
			echo $errorlist["pw2"];
		}
	echo "</td>";
	echo "</tr>";
	echo "<tr><td colspan='3'>";
		if (isset($errorlist["pw3"])){
			echo $errorlist["pw3"];
		}
	echo "</td></tr>";
	echo "<tr><td></td><td>";
	echo "<input type='submit' value='Activeren' name='zend'>";
	echo "</td><td>&nbsp;</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";

	echo '</div>';
	echo '</div>';
}


