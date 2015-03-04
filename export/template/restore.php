<?php
ob_start();
$rootpath = "../../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

//retval = "0";
$last_line = exec('cp letsgids_template.odt.orig letsgids_template.odt', $output, $retval);
if ($retval == "0")
{
 echo "Template successfully restored";
}
else
{
 echo "Problem with restore";
}
?>
