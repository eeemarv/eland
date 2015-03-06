<?php

require_once $rootpath . 'vendor/autoload.php';

// session name = color of the heroku postgres database

$db_dsn = getenv('HEROKU_POSTGRESQL_' . $session_name . '_URL');

$db = NewADOConnection($db_dsn);

unset($db_dsn);

$db->SetFetchMode(ADODB_FETCH_ASSOC);

if(getenv('ELAS_DB_DEBUG'))
{
	$db->debug = true;
}

require_once $rootpath . 'includes/inc_dbconfig.php';
