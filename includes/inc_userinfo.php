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

/** Provided functions:
 * get_user_maildetails($userid) 	Return the user with mailaddress if available
 * get_user_maildetails_by_login($login)Lookup the user with mailaddress by his login
 * get_users() 				Get an array of all users
 * get_user($id) 			Get an array with userdetails
 * get_user_letscode($id) 		Get the user letscode
 * get_user_by_login($login)		Get the user by login
 * get_user_by_letscode($letscode)	Get the userarray from a letscode
 * get_user_by_name($name)		Get the user by fullname (should return 1 result)
 * get_user_by_openid($openid)		Get the user by OpenID URL
 * get_userid_by_opendid($openid)	Get the userID by OpenID URL
 * get_contact($user)			Get all contact information for the user
 * get_contacttype($abbrev)		Get contacttype by abbreviation
 * get_user_mailarray($userid)	Get an array of all user mail addresses
 * get_letsgroups()			Get all the interlets Groups
 * get_letsgroup($id)			Get the letsgroup by id
 * get_prefixes				Return a list of all prefixes
*/

function get_letsgroups(){
	global $db;
	$query = "SELECT * FROM letsgroups ";
	$letsgroups = $db->GetArray($query);
	return $letsgroups;
}

function get_letsgroup($id){
	global $db;
	$query = "SELECT * FROM letsgroups WHERE id=$id";
	$letsgroup = $db->GetRow($query);
	return $letsgroup;
}

function get_prefixes(){
        global $db;
        $query = "SELECT * FROM letsgroups WHERE apimethod ='internal' AND prefix IS NOT NULL";
        $list_prefixes = $db->GetArray($query);
        return $list_prefixes;
}

function get_contact_by_email($email){
        global $db;
        $query = "SELECT * FROM contact WHERE value = '" .$email ."'";
        $contact = $db->GetRow($query);
        return $contact;
}

function get_contact($user){
        global $db;
        $query = "SELECT *, ";
        $query .= " contact.id AS cid, users.id AS uid, type_contact.id AS tcid, ";
        $query .= " type_contact.name AS tcname, users.name AS uname ";
        $query .= " FROM users, type_contact, contact ";
        $query .= " WHERE users.id=".$user;
        $query .= " AND contact.id_type_contact = type_contact.id ";
        $query .= " AND users.id = contact.id_user ";
        $query .= " AND contact.flag_public = 1";
        $contact = $db->GetArray($query);
        return $contact;
}

function get_contacttype($abbrev){
	global $db;
        $query = "SELECT * FROM type_contact WHERE abbrev = '" .$abbrev ."'";
	$contacttype = $db->GetRow($query);
        return $contacttype;
}

function get_user($id){
		return readuser($id);
}

function get_user_by_letscode($letscode){
	global $db;
        $query = "SELECT * FROM users ";
        $query .= "WHERE letscode = '" .$letscode ."'";//."' AND status <> 0";
        $user = $db->GetRow($query);
        return $user;
}

function get_user_by_login($login){
	global $db;
        $query = "SELECT * FROM users ";
        $query .= "WHERE login = '" .$login ."'";
        $user = $db->GetRow($query);
        return $user;
}

function get_user_by_name($name){
	global $db;
        $query = "SELECT * FROM users ";
        $query .= "WHERE (LOWER(fullname)) LIKE '%" .strtolower($name) ."%'";
	$user = $db->GetRow($query);
        return $user;
}

function get_user_by_openid($openid){
	global $db;
    $query = "SELECT * FROM openid";
    $query .= " WHERE openid = '" .$openid ."'";
	$openid_row = $db->GetRow($query);
	$user = get_user_maildetails($openid_row["user_id"]);
	return $user;
}

function get_userid_by_openid($openid){
	global $db;
        $query = "SELECT * FROM openid";
        $query .= " WHERE openid = '" .$openid ."'";
        $openid_row = $db->GetRow($query);
	return $openid_row["user_id"];
}

function get_user_maildetails($userid){
        global $db;
		$user = readuser($userid);
        $query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
        $contacts = $db->GetRow($query);
        $user["emailaddress"] = $contacts["value"];
        return $user;
}

function get_user_maildetails_by_login($login){
        global $db;
		$user = get_user_by_login($login);
		$userid = $user["id"];
		$query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
        $contacts = $db->GetRow($query);
        $user["emailaddress"] = $contacts["value"];
        return $user;
		//print_r $user;
}

function get_user_mailaddresses($userid){
        global $db;
		$user = readuser($userid);
        $query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
        $array= $db->GetArray($query);
		foreach ($array as $key => $value){
			$userto .= $value["value"] . ",";
		}
		return $userto;
}

function get_user_mailarray($userid){
        global $db;
		$user = readuser($userid);
        $query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
        $array= $db->GetArray($query);
        return $array;
}

function get_users(){
        global $db;
        $query = "SELECT * FROM users WHERE (users.status = 1 or users.status=2 or users.status = 3 or users.status = 4 )";
        $query .= " and users.accountrole <> 'guest' order by letscode";

        $list_users = $db->GetArray($query);
        return $list_users;
}

function get_user_letscode($id){
        global $db;
        $user = readuser($id);
        return $user['letscode'];
}
