<?php
/**
 * Class to perform eLAS apifunctions
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

/** Provided functions:
 * verify_apikey($apikey) // Verifies the apikey and returns 1 on success, 0 on failure
 * get_group_byapikey($apikey) // Get the group based on an incoming api key
 * generate_apikey() // Generate an api key
 * get_apikeys() // Get the api keys
*/

function generate_apikey(){
	$systemname = readconfigfromdb("systemname");
	$time = microtime();
	$genid = sha1("$systemname $time");
	return $genid;
}

function get_group_byapikey($apikey) {
    global $db;
	$query = "SELECT * FROM letsgroups WHERE apirecvkey = '" .$apikey ."'";
	$letsgroup = $db->GetRow($query);
	return $letsgroup;
}

function check_apikey($apikey,$type){
        global $db;
        $query = "SELECT apikey FROM apikeys WHERE apikey = '" .$apikey ."' AND type = '" .$type ."'";
        //echo $query;
        $myapikey = $db->GetRow($query);

        if($apikey == $myapikey["apikey"] && !empty($apikey)) {
                return 1;
        } else {
                return 0;
        }
}

function get_apikeys(){
	global $db;
        $query = "SELECT * FROM apikeys";
        $apikeys = $db->GetArray($query);
	return $apikeys;
}

?>
