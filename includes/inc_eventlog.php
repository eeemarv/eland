<?php

//Download the elas log in json format
function get_elaslog() {
	global $rootpath;
	global $provider;
	global $baseurl;
	
//	$logurl = $provider->logurl;
	
	$url = "$logurl/site?tag=" . readconfigfromdb("systemtag");
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$resp = curl_exec($ch);
	if(curl_errno($ch)) {
		$msg = 'Curl error: ' . curl_error($ch);
		log_event(0, 'Info', $msg);
	}

	curl_close($ch);
	
	//print $resp;
	
	# Write response to a file
	$file = "$rootpath/sites/$baseurl/json/eventlog.json";
	file_put_contents($file, $resp);
}


//Write log entry
function log_event($id,$type,$event){
	global $configuration;
    	global $db;
	global $elasdebug;
	global $dirbase;
	global $rootpath;
	
	$ip = $_SERVER['REMOTE_ADDR'];
 
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
   	$ts = date("Y-m-d H:i:s");
	$mytype = strtolower($type);
}

?>
