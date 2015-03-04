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

function ostatus_queue($message, $url) {
	global $db;

	// only queue if sharing is not disabled!
	if(readconfigfromdb("share_enabled") == 1) {
		$msg = substr($message, 0, 100);
		$query = "INSERT INTO ostatus_queue (message, url) VALUES ('" .$msg ."', '" .$url ."')";
		//echo $query;
		$db->Execute($query);
	}
}

function ostatus_post($message){
	global $db;
	#unction executeCurl($url, $message, $identicausername, $identicapassword){
		$url = readconfigfromdb("ostatus_url");
		$identicausername = readconfigfromdb("ostatus_user");
		$identicapassword = readconfigfromdb("ostatus_password");
		$group = "!" .readconfigfromdb("ostatus_group");
		//echo "Posting to $url as $identicausername with password $identicapassword ";

        // Set up and execute the curl process
        $curl_handle = curl_init();
        curl_setopt( $curl_handle, CURLOPT_URL, "$url/api/statuses/update.xml" );
        curl_setopt( $curl_handle, CURLOPT_CONNECTTIMEOUT, 2 );
        curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $curl_handle, CURLOPT_POST, 1 );
        curl_setopt( $curl_handle, CURLOPT_POSTFIELDS, "status=$message $group" );
        curl_setopt( $curl_handle, CURLOPT_USERPWD, "$identicausername:$identicapassword" );
        $buffer = curl_exec( $curl_handle );
        curl_close( $curl_handle );

        // check for success or failure
        if( empty( $buffer ) ) {
                return false;
                $status = 0;
        } else {
                return true;
                $status = 1;
        }

        return true;
}

function shorturl ( $url ) {
        // create a short url of the article link ( $shortlink ) using the http:// ur1.ca service
        $ch = curl_init();
        $timeout = 5;
        $shortenerurl = 'http://ur1.ca/';
        $articleurl = urlencode($url);

        curl_setopt( $ch, CURLOPT_URL, $shortenerurl );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "longurl=$articleurl" );
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION , 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);  // DO NOT RETURN HTTP HEADERS
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  // RETURN THE CONTENTS OF THE CALL
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
        $rawshortlinkoutput = curl_exec( $ch );
        curl_close( $ch );

        // extract the short url from the ur1.ca html output.  It's the link within the first <p> in the body.
        $shortlinkxml = @simplexml_load_string($rawshortlinkoutput);
        $shortlinkarray = $shortlinkxml->body->p[0]->a->attributes();

        return strval($shortlinkarray['href']);
}

?>
