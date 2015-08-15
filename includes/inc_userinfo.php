<?php
/**
 * Class to perform <purpose}
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

function get_prefixes()
{
	global $db;
	$query = "SELECT * FROM letsgroups WHERE apimethod ='internal' AND prefix IS NOT NULL";
	$list_prefixes = $db->GetArray($query);
	return $list_prefixes;
}

function get_user_by_letscode($letscode)
{
	global $db;
    return $db->GetRow('SELECT * FROM users WHERE letscode = \'' . $letscode . '\'');
}

function get_user_by_name($name)
{
	global $db;
        $query = "SELECT * FROM users ";
        $query .= "WHERE (LOWER(fullname)) LIKE '%" .strtolower($name) ."%'";
	$user = $db->GetRow($query);
        return $user;
}

function get_user_maildetails($userid)
{
        global $db;
		$user = readuser($userid);
        $query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
        $contacts = $db->GetRow($query);
        $user["emailaddress"] = $contacts["value"];
        return $user;
}

function get_user_mailaddresses($userid)
{
        global $db;
		$user = readuser($userid);
        $query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
        $array= $db->GetArray($query);
		foreach ($array as $key => $value){
			$userto .= $value["value"] . ",";
		}
		return $userto;
}


function get_users()
{
	global $db;
	$list_users = $db->GetArray("SELECT *
		FROM users
		WHERE status in (1, 2) 
			and users.accountrole <> 'guest' order by letscode");
	return $list_users;
}
