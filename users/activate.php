<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_mailfunctions.php");
require_once($rootpath."includes/inc_passwords.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];


include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	if (isset($_GET["id"])){
		$id = $_GET["id"];
		$user = get_user_maildetails($id);
		show_ptitle();
		if(isset($_POST["zend"])){
			$posted_list = array();
			$posted_list["pw1"] = $_POST["pw1"];
			$posted_list["pw2"] = $_POST["pw2"];
			$posted_list["adate"] = date("Y-m-d H:i:s");
			$errorlist = validate_input($posted_list,$configuration);
			if (!empty($errorlist)){
				show_pwform($errorlist, $id, $user);
			}else{
				sendactivationmail($posted_list["pw1"], $user, $s_id);
				sendadminmail($posted_list, $user);
				update_password($id, $posted_list);
				set_adate($id);
				saydone($posted_list, $user, $s_id);
				//redirect_view($id);
			}
		}else{
			show_pwform($errorlist, $id, $user);
		}
	}else{
		//redirect_overview();
	}
}else{
	redirect_login($rootpath);
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_overview(){
	header("Location: overview.php");
}

function sendadminmail($posted_list, $user){
        global $configuration;
        if (!empty($configuration["mail"]["admin"])){
                $mailfrom .= trim($configuration["mail"]["from_address"]);
                $mailto .= trim($configuration["mail"]["admin"])."\r\n";
        }else {
                 Echo "No admin adress set in config, not sending";
                 return 0;
        }

        $mailsubject = "[";
        $mailsubject .= $configuration["system"]["systemtag"];
        $mailsubject .= "] eLAS account activatie";

        $mailcontent  = "*** Dit is een automatische mail van het eLAS systeem van ";
        $mailcontent .= $configuration["system"]["systemtag"];
        $mailcontent .= " ***\r\n\n";
	$mailcontent .= "De account ";
	$mailcontent .= $user["login"];
	$mailcontent .= " werd geactiveerd met een nieuw passwoord.\n";
	if (!empty($user["emailaddress"])){	
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
    return $result;
}

function saydone($posted_list, $user, $s_id){
	global $_SESSION;
	//log it
	$userlogin = $user["login"];
	log_event($s_id,"Act","Account $userlogin activated");
	setstatus("Account $userlogin geactiveerd", 0);
}

function redirect_view($id){
	header("Location: view.php?id=".$id."");
}

function validate_input($posted_list,$configuration){
	$errorlist = array();
	if (empty($posted_list["pw1"]) || (trim($posted_list["pw1"]) == "")){
		$errorlist["pw1"] = "<font color='#F56DB5'>Vul <strong>paswoord</strong> in!</font>";
	}
        $pwscore = Password_Strength($posted_list["pw1"]);
        $pwreqscore = $configuration["system"]["pwscore"];
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

function show_pwform($errorlist, $id, $user){
	$pw = GeneratePassword();
	echo "<div class='border_b'>";
	echo "<form action='activate.php?id=".$id."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>User</td>";
	echo "<td valign='top'>";
	echo $user["name"];
	echo "</td></tr>";
	echo "<tr><td valign='top' align='right'>E-mail</td>";
	echo "<td valign='top'>";
	echo $user["emailaddress"];
	echo "</td></tr>";
	echo "<tr><td valign='top' align='right'>Paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='text' name='pw1' size='30' value='";
	echo $pw;
	echo "'>";
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
        echo "'>";
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
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' value='Activeren' name='zend'>";
	echo "</td><td>&nbsp;</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";
}


function show_ptitle(){
	echo "<h1>Account activeren</h1>";
}
function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}
include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
