<?php
# Config file handling
# the config file is named config/elas.conf.php
# we will load it into the array $configuration

// Get the baseurl, used for several things including multisite
$baseurl = $_SERVER['HTTP_HOST'];

# Get rid of missing rootpath errors
if(!isset($rootpath)){
	$rootpath = "";
}

if(is_dir($rootpath."sites/$baseurl")){
	$dirbase = $baseurl;
} else {
	$dirbase = "default";
}

$xml_config = $rootpath ."sites/$dirbase/config/elas.xml";

if(file_exists($xml_config)){
	$xmlconfig = simplexml_load_file("$xml_config");
	$configuration["db"]["dsn"] = $xmlconfig->dbdsn;
	$configuration["system"]["sessionname"] = $xmlconfig->sessionname;
	$configuration["system"]["timezone"] = $xmlconfig->timezone;
	$configuration["hosting"]["enabled"] = $xmlconfig->hosting;
	if(empty($xmlconfig->dbdriver)){
		$xmlconfig->dbdriver = "postgres";
	}
} else {
	echo "<h1>Configuratie niet gevonden</h1>";
	echo "<p>" . $xml_config . "</p>";
	exit(1);
}

// Check for presence of the $configuration variable: if not present, the configuration file should be created and we redirect the flow to the setup page
if (!isset($configuration["db"]["dsn"])&& ($nocheckconfig != TRUE )){
    	header("Location: ".$rootpath."noconfig.php");
	//echo "$baseurl - $dirbase";
	exit;
}

?>
