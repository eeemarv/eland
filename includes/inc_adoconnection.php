<?php

require_once('adodb/adodb-errorpear.inc.php');
require_once("adodb/adodb.inc.php");
// require_once($rootpath."includes/inc_config.php");

//$db_dsn=$xmlconfig->dbdriver ."://" . $xmlconfig->dbuser .":" .$xmlconfig->dbpass . "@" .$xmlconfig->dbhost . "/" .$xmlconfig->dbname;

//$parseddsn=parse_url($db_dsn);

//$db = NewADOConnection($db_dsn);

/**
 * session name = color of the heroku postgres database
 */
$db_dsn = ($session_name == 'ELASDEFAULT') ? 'DATABASE_URL' : 'HEROKU_POSTGRESQL_' . $session_name . '_URL';
$db_dsn = getenv($db_dsn);

$db = NewADOConnection($db_dsn);

unset($db_dsn);

$db->SetFetchMode(ADODB_FETCH_ASSOC);

if(getenv('ELAS_DB_DEBUG')){
	$db->debug = true;
}

function getadoerror(){
	$e = ADODB_Pear_Error();
        if(is_object($e)){
                        return $e->message;
        }
	return FALSE;
}

require_once($rootpath."includes/inc_dbconfig.php");
require_once($rootpath."includes/inc_legacyconfig.php");
?>
