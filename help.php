<?php
ob_start();
//$ptitle="login";
$rootpath = "./";
$role = 'anonymous';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

require_once($rootpath."includes/inc_header.php");

echo "<table border='0' width='100%'><tr><td><h1>eLAS Help</h1>";
echo "</td><td align='right'>";
echo "</td></tr></table>";

if(isset($s_id)){
	$user = get_user_maildetails($s_id);
}

if(isset($_POST["zend"])){
        $posted_list = array(); 
	$posted_list["login"] = mysql_escape_string($_POST["login"]);
	$posted_list["email"] = mysql_escape_string($_POST["email"]);
	$posted_list["subject"] = mysql_escape_string($_POST["subject"]);
	$posted_list["omschrijving"] = mysql_escape_string($_POST["omschrijving"]);
	$posted_list["browser"] = mysql_escape_string($_POST["browser"]);
        $error_list = validate_input($posted_list);

        if(!empty($error_list)){
                show_form($user["login"],$user["emailaddress"],$error_list,$posted_list);
        }else{
		HelpMail($posted_list,$rootpath);
        }

}else{
		// no mail for demo site or when it not configured
      if (readconfigfromdb("mailenabled") !== "1" ){
         Echo "E-mail functies zijn uitgeschakeld door de beheerder, je kan dit formulier niet gebruiken";
      	return 0;
      } else {
         show_form($user["login"],$user["emailaddress"],$error_list,$posted_list);
      }
}

echo "<small><i>Opgelet: je kan vanuit het loginscherm zelf een nieuw password aanvragen met je e-mail adres!</i></small>";

include $rootpath . 'includes/inc_footer.php';

function show_form($id,$email,$error_list,$posted_list){
	$browser = $_SERVER['HTTP_USER_AGENT'];
        echo "<form action='help.php' method='post'>";
	echo "<input type='hidden' name='browser' id='browser' value='" .$browser ."'>";
        echo "<table cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr><td>";
        echo "Loginnaam";
        echo "</td><td>";
	if(isset($id)){
		echo "<input type='text' name='login' size='30' value=\"$id\">";
	} else {
			if(empty($posted_list["login"])){
        		echo "<input type='text' name='login' size='30'>";
        	} else {
        		echo "<input type='text' name='login' size='30' value='" .$posted_list["login"] ."'>";
        	}
	}
        echo "</td><td>";
	if(!empty($error_list["login"])){
			echo $error_list["login"];
        }
        echo "</td></tr>";
        echo "<tr><td>";
        echo "E-mail adres<br><small><i>(Dit adres moet in eLAS geregistreerd staan)</i></small>";
        echo "</td><td>";
	if(isset($email)){
			echo "<input type='text' name='email' size='30' value='" .$email ."'>";
	} else {
			if(empty($posted_list["email"])){
	        echo "<input type='text' name='email' size='30'>";
	      } else {
	      	echo "<input type='text' name='email' size='30' value='" .$posted_list["email"] ."'>";
	      }
	}
        echo "</td><td>";
	if(!empty($error_list["email"])){
	                echo $error_list["email"];
        }
        echo "</td></tr>";
	// Subject line
	echo "<tr><td>";
	echo "Onderwerp<br><small><i>(verplicht, bv. Inloggen lukt niet)</i></small><br>";
	echo "</td><td>";
	if(empty($posted_list["subject"])){
        echo "<input type='text' name='subject' size='60'>";
   } else {
   	echo "<input type='text' name='subject' size='60' value='" .$posted_list["subject"] ."'>";
   }
	echo "</td><td>";
        if(!empty($error_list["subject"])){
                        echo $error_list["subject"];
        }
        echo "</td></tr>";
	// subject
	echo "<tr><td>";
	echo "Omschrijving van je probleem:<br>";
	echo "</td><td>";
	if(empty($posted_list["omschrijving"])){
		echo "<TEXTAREA NAME='omschrijving' COLS=60 ROWS=6></TEXTAREA>";
	} else {
		echo "<TEXTAREA NAME='omschrijving' COLS=60 ROWS=6>" .$posted_list["omschrijving"] ."</TEXTAREA>";
	}
	echo "</td></tr>";
	echo "<tr><td>";
	if(!empty($error_list["omschrijving"])){
                        echo $error_list["omschrijving"];
	}
	echo "</td></tr>";
	echo "<tr><td></td><td>";

	echo "<input type='submit' name='zend' value='Verzenden'>";
	echo "</td><td>&nbsp;</td></tr></table>";
	echo "</form>";
}

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
	if(empty($posted_list["omschrijving"])){
		$error_list["omschrijving"] = "<font color='red'> Geef een <strong>omschrijving</strong> van je probleem</font>";
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
   	global $configuration;
	global $elas;
	global $elasversion;

	$mailfrom .= "From: " .trim($posted_list['email']);
        if (!empty(readconfigfromdb("support"))){
		$mailto .= trim(readconfigfromdb("support"))."\r\n";
        }else {
		 Echo "No support adress set in config, not sending";
		 return 0;
	}

	$mailsubject = readconfigfromdb("systemtag") ." - " .$posted_list['subject'];

        $mailcontent  = "-- via de eLAS website werd hetvolgende probleem gemeld --\r\n";
	$mailcontent .= "E-mail: {$posted_list['email']}\r\n";
	$mailcontent .= "Login:  {$posted_list['login']}\r\n";
	$mailcontent .= "Omschrijving:\r\n";
	$mailcontent .= "{$posted_list['omschrijving']}\r\n";
	$mailcontent .= "\r\n";
	$mailcontent .= "User Agent:\r\n";
        $mailcontent .= "{$posted_list['browser']}\r\n";
	$mailcontent .= "\r\n";
	$mailcontent .= "eLAS versie: " .$elas->version ."-" .$elas->branch ."-r" .$elas->revision ."\r\n";
	$mailcontent .= "Webserver: " .gethostname() ."\r\n";

	echo "Bezig met het verzenden naar $mailto ...\n";
	// sendemail
        mail($mailto,$mailsubject,$mailcontent,$mailfrom);
	echo "OK\n";
	setstatus("Support mail verstuurd", 0);
	echo "<script type=\"text/javascript\">self.close();</script>";
}
