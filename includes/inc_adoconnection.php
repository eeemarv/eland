<?php

require_once $rootpath . 'vendor/autoload.php';

$db = NewADOConnection(getenv('DATABASE_URL'));

if (isset($session_name) && $session_name)
{
	$db->Execute('set schema ' . $session_name);
}

$db->SetFetchMode(ADODB_FETCH_ASSOC);

if(getenv('ELAS_DB_DEBUG'))
{
	$db->debug = true;
}

require_once $rootpath . 'includes/inc_dbconfig.php';
