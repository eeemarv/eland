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

