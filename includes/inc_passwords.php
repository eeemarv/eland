<?php
/**
 * Class to perform eLAS password functions
 *
 * This file is part of eLAS http://elas.vsbnet.be
 *
 * Copyright(C) 2009 Guy Van Sanden <guy@vsbnet.be>
 *
 * eLAS is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the  * GNU General Public License for more details.
*/
/** Provided functions:
 * Password_Strength($password, $username = null)	Check the strength of a password
 * generatePassword ($length = 10)i			Generate a random password
 * update_password($id, $posted_list)			Write password to database
 * sendactivationmail($password, $user,$s_id)		Send the password to the user
 * sendpasswordresetemail($password, $user,$s_id)	Send the password reset message to the user
*/

function sendpasswordresetemail($password, $user,$s_id){
	global $baseurl;
	$mailfrom = readconfigfromdb("from_address");

        if (!empty($user["emailaddress"])){
                $mailto = $user["emailaddress"];
        } else {
                echo "<p><b>Geen E-mail adres bekend voor deze gebruiker, stuur het wachtwoord op een andere manier door!</b></p>";
                return 0;
        }

	$systemtag = readconfigfromdb("systemtag");
        $mailsubject = "[";
        $mailsubject .= $systemtag;
        $mailsubject .= "] eLAS account";

        $mailcontent  = "*** Dit is een automatische mail van het eLAS systeem van ";
        $mailcontent .= $systemtag;
        $mailcontent .= " ***\r\n\n";
        $mailcontent .= "Beste ";
        $mailcontent .= $user["name"];
        $mailcontent .= "\n\n";
        $mailcontent .= "eLAS heeft voor jouw een nieuw wachtwoord ingesteld, zodat je (weer) kan inloggen op http://$baseurl.\n";
	$mailcontent .= "\neLAS is een elektronisch systeem voor het beheer van vraag & aanbod en transacties.
Er werd voor jou een account aangemaakt waarmee je kan inloggen en je gegevens beheren.\n\n";
        $mailcontent .= "\n-- Account gegevens --\n";
        $mailcontent .= "Login: ";
        $mailcontent .= $user["login"];
        $mailcontent .= "\nPasswoord: ";
        $mailcontent .= $password;
        $mailcontent .= "\n-- --\n\n";

		$openids = get_openids($user["id"]);
       	$mailcontent .= "Of log in met een OpenID account (indien gelinked): \n";
		foreach($openids as $value){
			$mailcontent .= " * " .$value["openid"] ."\n";
		}
		$mailcontent .= "\n";

        $mailcontent .= "Als je nog vragen of problemen hebt, kan je terecht bij ";
        $mailcontent .= readconfigfromdb("support");
        $mailcontent .= "\n\n";
        $mailcontent .= "Met vriendelijke groeten.\n\nDe eLAS Account robot\n";

        //echo "Bezig met het verzenden naar $mailto...\n";
        sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
        // log it
        log_event($s_id,"Mail","Password reset email sent to $mailto");
        //echo "OK<br>";
		$status = "OK - Een nieuw wachtwoord is verstuurd via email";
		return $status;
}

function sendactivationmail($password, $user){
	global $baseurl, $s_id;
	$mailfrom = readconfigfromdb("from_address");

        if (!empty($user["mail"])){
            $mailto = $user["mail"];
        } else {
			$alert->warning("Geen E-mail adres bekend voor deze gebruiker, stuur het wachtwoord op een andere manier door!");
			return 0;
        }

	$systemtag = readconfigfromdb("systemtag");
        $systemletsname = readconfigfromdb("systemname");
        $mailsubject = "[";
        $mailsubject .= $systemtag;
        $mailsubject .= "] eLAS account activatie voor $systemletsname";

        $mailcontent  = "*** Dit is een automatische mail van het eLAS systeem van ";
        $mailcontent .= $systemtag;
        $mailcontent .= " ***\r\n\n";
        $mailcontent .= "Beste ";
        $mailcontent .= $user["name"];
        $mailcontent .= "\n\n";

        $mailcontent .= "Welkom bij Letsgroep $systemletsname";
		$mailcontent .= ". Surf naar $systemtag via http://$baseurl" ;
        $mailcontent .= " en meld je aan met onderstaande gegevens.\n";
        $mailcontent .= "\n-- Account gegevens --\n";
        $mailcontent .= "Login: ";
        $mailcontent .= $user["login"];
        $mailcontent .= "\nPasswoord: ";
        $mailcontent .= $password;
        $mailcontent .= "\n-- --\n\n";

        $openids = get_openids($user["id"]);
       	$mailcontent .= "Of log in met een OpenID account (indien gelinked): \n";
		foreach($openids as $value){
			$mailcontent .= " * " .$value["openid"] ."\n";
		}
		$mailcontent .= "\n";

	$mailcontent .= "Met eLAS kan je je gebruikersgevens, vraag&aanbod en lets-transacties";
	$mailcontent .= " zelf bijwerken op het Internet.";
        $mailcontent .= "\n\n";
        
		$mailcontent .= "Als je nog vragen of problemen hebt, kan je terecht bij ";
		$mailcontent .= readconfigfromdb("support");
		$mailcontent .= "\n\n";
		$mailcontent .= "Veel plezier bij het letsen! \n\n De eLAS Account robot\n";

        //echo "Bezig met het verzenden naar $mailto...\n";
        sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
        // log it
        log_event($s_id,"Mail","Activation mail sent to $mailto");
        //echo "OK<br>";
		echo "OK - Activatiemail verstuurd";
}

function get_openids($user_id){
	global $db;
	$query = "SELECT openid FROM openid WHERE user_id = " .$user_id;
	$result = $db->Execute($query);
	return $result;
}

function update_password($id, $posted_list){
	global $db;
	global $s_id;

	$posted_list["password"]=hash('sha512',$posted_list["pw1"]);
	$posted_list["mdate"] = date("Y-m-d H:i:s");
	$result = $db->AutoExecute("users", $posted_list, 'UPDATE', "id=$id");
	
	readuser($id, true);
	
	if($result == true){
		log_event($s_id,"Password","Password for userID $id changed by ID $s_id");
		setstatus('Passwoord gewijzigd',0);
	} else {
		setstatus('Passwoord niet gewijzigd',0);
	}

	log_event($s_id,"System","");
        return $result;
}

function generatePassword ($length = 10)
{
    srand((double)microtime()*1000000);
    $number = rand(0,9);

    $vowels = array("a", "e", "i", "o", "u");
    $cons = array("b", "c", "d", "g", "h", "j", "k", "l", "m", "n", "p", "r", "s", "t", "u", "v", "w", "tr",
    "cr", "br", "fr", "th", "dr", "ch", "ph", "wr", "st", "sp", "sw", "pr", "sl", "cl");

    $num_vowels = count($vowels);
    $num_cons = count($cons);

    for($i = 0; $i < $length; $i++){
        $password .= $cons[rand(0, $num_cons - 1)] . $vowels[rand(0, $num_vowels - 1)];
    }

    $word = substr($password, 0, ($length - 1));
    return $word . $number;
}

function Password_Strength($password, $username = null)
{
    if (!empty($username))
    {
        $password = str_replace($username, '', $password);
    }

    $strength = 0;
    $password_length = strlen($password);

    if ($password_length < 4)
    {
        return $strength;
    }

    else
    {
        $strength = $password_length * 4;
    }

    for ($i = 2; $i <= 4; $i++)
    {
        $temp = str_split($password, $i);

        $strength -= (ceil($password_length / $i) - count(array_unique($temp)));
    }

    preg_match_all('/[0-9]/', $password, $numbers);

    if (!empty($numbers))
    {
        $numbers = count($numbers[0]);

        if ($numbers >= 3)
        {
            $strength += 5;
        }
    }

    else
    {
        $numbers = 0;
    }

    preg_match_all('/[|!@#$%&*\/=?,;.:\-_+~^¨\\\]/', $password, $symbols);

    if (!empty($symbols))
    {
        $symbols = count($symbols[0]);

        if ($symbols >= 2)
        {
            $strength += 5;
        }
    }

    else
    {
        $symbols = 0;
    }

    preg_match_all('/[a-z]/', $password, $lowercase_characters);
    preg_match_all('/[A-Z]/', $password, $uppercase_characters);

    if (!empty($lowercase_characters))
    {
        $lowercase_characters = count($lowercase_characters[0]);
    }

    else
    {
        $lowercase_characters = 0;
    }

    if (!empty($uppercase_characters))
    {
        $uppercase_characters = count($uppercase_characters[0]);
    }

    else
    {
        $uppercase_characters = 0;
    }

    if (($lowercase_characters > 0) && ($uppercase_characters > 0))
    {
        $strength += 10;
    }

    $characters = $lowercase_characters + $uppercase_characters;

    if (($numbers > 0) && ($symbols > 0))
    {
        $strength += 15;
    }

    if (($numbers > 0) && ($characters > 0))
    {
        $strength += 15;
    }

    if (($symbols > 0) && ($characters > 0))
    {
        $strength += 15;
    }

    if (($numbers == 0) && ($symbols == 0))
    {
        $strength -= 10;
    }

    if (($symbols == 0) && ($characters == 0))
    {
        $strength -= 10;
    }

    if ($strength < 0)
    {
        $strength = 0;
    }

    if ($strength > 100)
    {
        $strength = 100;
    }

    return $strength;
}

?>
