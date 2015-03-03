<?php

$dbconfig = array();
$dbparameters = array();

if(!empty($redis)){
	//echo "Fetching config from redis";
	$rediskey = $session_name . "::config";

	if($redis->exists($rediskey)){
		readredistoglobal();
	} else {
		loadredisfromdb();
		readredistoglobal();
	}
} else {

	$query = "SELECT * FROM config";
	$dbconfig = $db->GetArray($query);

	
	
	$query = "SELECT * FROM parameters";
	$dbparameters = $db->GetArray($query);
}


// Fetch configuration keys from the database
function readconfigfromdb($searchkey){
    global $db;
    global $dbconfig;

 //   return $dbconfig[$searchkey];

    foreach ($dbconfig as $key => $list) {
		
		if($list['setting'] == $searchkey) {
			return $list['value'];
		}
    } 
}

function readredistoglobal(){
	global $redis;
	global $xmlconfig;
	global $dbconfig;
	global $dbparameters;
	global $session_name;
	
	$rediskey = $session_name . "::config";
	$result = $redis->get($rediskey);
	//echo $result;
	$dbconfig = unserialize($result);
	
	$rediskey = $session_name . "::parameters";
	$dbparameters = unserialize($redis->get($rediskey));
}

function loadredisfromdb(){
	global $db;
	global $redis;
	global $xmlconfig;
	global $dbconfig;
	global $dbparameters;
	global $session_name;

	if (empty($redis)){
		return;
	}

	$rediskey = $session_name . "::config";
	$query = "SELECT * FROM config";
	$mydbconfig = serialize($db->GetArray($query));
	$redis->set($rediskey, $mydbconfig);
	$redis->expire($rediskey, 1800);
	
	$rediskey = $session_name . "::parameters";
	$query = "SELECT * FROM parameters";
	$mydbparameters = serialize($db->GetArray($query));
	$redis->set($rediskey, $mydbparameters);
	$redis->expire($rediskey, 1800);
}

function writeconfig($setting,$value){
	global $db;
	$query = "UPDATE config SET value ='" . $value . "' WHERE setting = '" . $setting . "'";
	$db->Execute($query);
	loadredisfromdb();
}

function readparameter($searchkey){
	global $dbparameters;
	foreach ($dbparameters as $key => $list) {
		if($list['parameter'] == $searchkey) {
			return $list['value'];
		}
    }
}
