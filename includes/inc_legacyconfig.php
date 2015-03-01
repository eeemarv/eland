<?php
//require_once($rootpath."includes/inc_dbconfig.php");

// Handle legacy config settings from the original config file
$configuration["system"]["currency"] = readconfigfromdb("currency");
$configuration["system"]["systemtag"] = readconfigfromdb("systemtag");
$configuration["system"]["systemname"] = readconfigfromdb("systemname");
$configuration["system"]["sessionname"] = readconfigfromdb("sessionname");
$configuration["system"]["emptypasswordlogin"] = readconfigfromdb("emptypasswordlogin");
$configuration["system"]["timezone"] = readconfigfromdb("timezone");
$configuration["system"]["pwscore"] = readconfigfromdb("pwscore");
$configuration["system"]["maintenance"] = readconfigfromdb("maintenance");
$configuration["system"]["newuserdays"] = readconfigfromdb("newuserdays");
//$configuration["cron"]["saldofreqdays"] = readconfigfromdb("saldofreqdays");
//$configuration["cron"]["adminmsgexp"]["enabled"] = readconfigfromdb("adminmsgexp");
//$configuration["cron"]["adminmsgexpfreqdays"] = readconfigfromdb("adminmsgexpfreqdays");
//$configuration["cron"]["msgexpwarnenabled"] = readconfigfromdb("msgexpwarnenabled");
//$configuration["cron"]["msgexpwarningdays"] = readconfigfromdb("msgexpwarningdays");
//$configuration["cron"]["msgexpcleanupdays"] = readconfigfromdb("msgexpcleanupdays");
$configuration["users"]["minlimit"] = readconfigfromdb("minlimit");
$configuration["mail"]["enabled"] = readconfigfromdb("mailenabled");
$configuration["mail"]["admin"] = readconfigfromdb("admin");
$configuration["mail"]["support"] = readconfigfromdb("support");
$configuration["mail"]["from_address"] = readconfigfromdb("from_address");
$configuration["mail"]["from_address_transactions"] = readconfigfromdb("from_address_transactions");

?>
