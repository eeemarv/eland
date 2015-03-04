<?php
# Perform a DB update from CRON

function doupgrade($version){
	global $db;
	global $configuration;

	$ran = 0;
	//log_event("","DB","Running DB upgrade $version");

	switch($version){
		case 30000:
			$ran = 1;
            break;

        case 30001:
			$query = "ALTER TABLE transactions ALTER COLUMN transid TYPE character varying(200)";
			executequery($query);
            $ran = 1;
            break;

        case 31000:
			$query = "DELETE FROM letsgroups WHERE id = 0";
			executequery($query);
            $ran = 1;
            break;

        case 31002:
			$query = "INSERT INTO config (category,setting,value,description,default) VALUES('system','ets_enabled','0', 'Enable ETS functionality', 0)";
			executequery($query);
            $ran = 1;
            break;

		case 31003:
			// FIXME: We need to repeat 2205 and 2206 to fix imported transactions after those updates
			break;
}

	// Finay, update the schema version
	if($ran == 1){
		echo "Executed upgrade version $version\n";
		$query = "UPDATE parameters SET value = $version WHERE parameter = 'schemaversion'";
		executequery($query);
		return TRUE;
	} else {
		return FALSE;
	}
}

function executequery($query) {
	global $db;
	global $elas;
	global $configuration;
	global $elasversion;

	echo "\nExecuting: $query: ";
	$result = $db->Execute($query);

	if($result == FALSE){
			echo "FAILED executing $query\n";
			log_event("","DB","FAILED upgrade query $query");

			$mailto = readconfigfromdb("admin");
			$mailfrom = readconfigfromdb("admin");
			// Include redmine in each report
			$mailto .= ", support@taurix.net";
			$mailsubject = "[eLAS " . readconfigfromdb("systemtag") ."] DB Update FAILED";

			$mailcontent = "A query failed during the upgrade of your eLAS database!\n  This report has been copied to the eLAS developers.";
			$mailcontent .= "\nFailed query: $query\n";
			$mailcontent .= "\r\n";
			$mailcontent .= "eLAS versie: " .$elas->version ."-" .$elas->branch ."-r" .$elas->revision ."\r\n";
			//$mailcontent .= "eLAS version: " .$elasversion ."\r\n";

			$mailcontent .= "De eLAS update robot";

			if($elas->branch != 'dev'){
				sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
			}
			exit;
	} else {
			echo "OK\n";
			log_event("","DB", "OK upgrade query");
	}
	return $result;
}

function generate_oldtransid(){
        global $baseurl;
        global $s_id;
        $genid = "E1" .sha1($s_id .microtime()) .$_SESSION["id"] ."@" . $baseurl;
        return $genid;
}

?>
