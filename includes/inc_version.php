<?php
// Debug eLAS, enable extra logging
$elasdebug = (getenv('ELAS_DEBUG'))? 1 : 0;

// release file (xml) not loaded anymore.
$elasversion = '3.1.17';  // was eLAS 3.1.17 in release file.
$schemaversion= 31000;  // no new versions anymore, release file is not read anymore.
$soapversion = 1200;
$restversion = 1;
