<?php
/**
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

function sendactivationmail($password, $user)
{
	global $base_url, $s_id, $alert, $systemname, $systemtag;

	$from = readconfigfromdb('from_address');

	if (!empty($user["mail"]))
	{
		$to = $user["mail"];
	}
	else
	{
		$alert->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het wachtwoord op een andere manier door!');
		return 0;
	}

	$subject = '[';
	$subject .= $systemtag;
	$subject .= '] account activatie voor ' . $systemname;

	$content  = "*** Dit is een automatische mail van ";
	$content .= $systemname;
	$content .= " ***\r\n\n";
	$content .= 'Beste ';
	$content .= $user['name'];
	$content .= "\n\n";

	$content .= "Welkom bij Letsgroep $systemname";
	$content .= '. Surf naar ' . $base_url;
	$content .= " en meld je aan met onderstaande gegevens.\n";
	$content .= "\n-- Account gegevens --\n";
	$content .= "Login: ";
	$content .= $user['letscode']; 
	$content .= "\nPasswoord: ";
	$content .= $password;
	$content .= "\n-- --\n\n";

	$content .= "Je kan je gebruikersgevens, vraag&aanbod en lets-transacties";
	$content .= " zelf bijwerken op het Internet.";
	$content .= "\n\n";

	$content .= "Als je nog vragen of problemen hebt, kan je terecht bij ";
	$content .= readconfigfromdb('support');
	$content .= "\n\n";
	$content .= "Veel plezier bij het letsen! \n";

	sendemail($from,$to,$subject,$content);

	log_event($s_id, 'Mail', 'Activation mail sent to ' . $to);
}

function password_strength($password, $username = null)
{
    if (!empty($username))
    {
        $password = str_replace($username, '', $password);
    }

    $strength = 0;
    $password_length = strlen($password);

    if ($password_length < 5)
    {
        return $strength;
    }
    else
    {
        $strength = $password_length * 9;
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

        if ($numbers >= 1)
        {
            $strength += 8;
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

        if ($symbols >= 1)
        {
            $strength += 8;
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
