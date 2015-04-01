<?php

require_once $rootpath . 'vendor/autoload.php';

$db = NewADOConnection(getenv('DATABASE_URL'));

$db->Execute('set search_path to ' . (($schema) ?: 'public'));

$db->SetFetchMode(ADODB_FETCH_ASSOC);

if(getenv('ELAS_DB_DEBUG'))
{
	$db->debug = true;
}

require_once $rootpath . 'includes/inc_dbconfig.php';
