<?php
/**
 * Class to perform eLAS token manipulations
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/
//require_once($rootpath."includes/inc_default.php");
//require_once($rootpath."includes/inc_adoconnection.php");

/** Provided functions:
*/

// Enable logging
global $rootpath;

function generate_token($type){
	global $db;
	$targetdate = time() + (10 * 60);
	$testdate = date('Y-m-d H:i:s', $targetdate);
	$token = "elasv2" .md5(microtime());

	$posted_list["token"] = $token;
	$posted_list["validity"] = $testdate;	
	$posted_list["type"] = $type;

        if($db->AutoExecute("tokens", $posted_list, 'INSERT') == FALSE){
                $token = "";
        }

	return $token;
}

function verify_token($token,$type){
	global $db;
	$testdate = date('Y-m-d H:i:s', time());
	$query = "SELECT * FROM tokens WHERE token = '" .$token ."' AND validity > '" .$testdate ."' AND type = '" .$type ."'";
	//print $query;
	// AND validity > '" .$testdate ."'";

	$mytoken = $db->GetRow($query);
	//print "Mytoken = " .$mytoken["token"] ." - incoming token = " . $token;
	if($mytoken["token"] == $token){
		return 0;
	} else {
		return 1;
	}
}

?>
