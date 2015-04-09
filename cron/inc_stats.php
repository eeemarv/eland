<?php

function update_stats()
{
	global $db;
	echo "Running update_stats\n";

	// Store a count of all active users
	$query = "SELECT * FROM users WHERE status = 1";
	$allusers = $db->Execute($query);
	$usercount = $allusers->RecordCount();
	save_stat("activeusers", $usercount);

	// Store the total number of transactions
	$query = "SELECT * FROM transactions";
	$alltrans = $db->Execute($query);
	$transcount = $alltrans->RecordCount();
	save_stat("totaltransactions", $transcount);
}

function save_stat($key,$value)
{
	global $db;

	$query = "UPDATE stats SET value = $value WHERE key = '" .$key ."'";
	$result = $db->Execute($query);

	if($result == FALSE){
		log_event("","Cron","ERROR writing stat $key");
	} else {
		log_event("","Cron","Updated stat $key");
	}
}
